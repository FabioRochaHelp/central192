<?php

declare(strict_types=1);

namespace App\Support\Operations;

use App\Domain\Operations\Enums\ShiftStatus;
use App\Integrations\Tracking\Contracts\TrackingProvider;
use App\Integrations\Tracking\DTOs\TrackingPosition;
use App\Models\Shift;
use App\Models\Vehicle;
use App\Models\VehiclePosition;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Throwable;

/** Viaturas visíveis no mapa tático: turno vigente (disponível ou empenhado). */
final class TacticalMapVehicleQuery
{
    private const string LIVE_POSITIONS_CACHE_PREFIX = 'tracking.positions.by_unit';

    /** @return list<ShiftStatus> */
    public static function mapVisibleShiftStatuses(): array
    {
        return [
            ShiftStatus::Disponivel,
            ShiftStatus::Empenhado,
        ];
    }

    public static function vehicleIsOnMap(int $vehicleId, ?CarbonInterface $at = null): bool
    {
        return self::vehiclesOnMapQuery($at)->whereKey($vehicleId)->exists();
    }

    public static function vehicleIsOnDispatch(int $vehicleId, ?CarbonInterface $at = null): bool
    {
        return self::activeShiftStatus($vehicleId, $at) === ShiftStatus::Empenhado;
    }

    public static function activeShiftStatus(int $vehicleId, ?CarbonInterface $at = null): ?ShiftStatus
    {
        $at ??= now();

        $shift = Shift::query()
            ->where('vehicle_id', $vehicleId)
            ->where('starts_at', '<=', $at)
            ->where('ends_at', '>=', $at)
            ->whereIn('status', self::mapVisibleShiftStatusValues())
            ->orderByDesc('starts_at')
            ->first();

        return $shift?->status;
    }

    /** @return Builder<Vehicle> */
    public static function vehiclesOnMapQuery(?CarbonInterface $at = null): Builder
    {
        $at ??= now();

        return Vehicle::query()
            ->whereNull('deleted_at')
            ->whereHas(
                'shifts',
                fn (Builder $query): Builder => self::applyActiveShiftScope($query, $at),
            );
    }

    /** @return Collection<int, array<string, mixed>> */
    public static function mapPayload(?int $municipioId = null, ?CarbonInterface $at = null): Collection
    {
        $at ??= now();

        $vehicles = self::vehiclesOnMapQuery($at)
            ->with('position')
            ->when($municipioId !== null, fn (Builder $query): Builder => $query->where('municipio_id', $municipioId))
            ->orderBy('prefix')
            ->get();

        if ($vehicles->isEmpty()) {
            return collect();
        }

        $provider = app(TrackingProvider::class);
        $liveByUnit = self::cachedLivePositions($provider);

        return $vehicles
            ->map(fn (Vehicle $vehicle): ?array => self::resolveMapVehicle($provider, $vehicle, $liveByUnit, $at))
            ->filter()
            ->values();
    }

    /** @return array<string, mixed> */
    public static function formatMapVehicle(object $position): array
    {
        $shiftStatus = self::resolveShiftStatus($position->shift_status ?? null);

        return [
            'vehicle_id' => $position->vehicle_id,
            'prefix' => $position->prefix ?? $position->plate ?? 'VTR',
            'lat' => (float) $position->latitude,
            'lng' => (float) $position->longitude,
            'speed_kmh' => (float) ($position->speed_kmh ?? 0),
            'fix_time' => $position->fix_time ? Carbon::parse($position->fix_time)->format('H:i:s') : null,
            'valid' => (bool) $position->valid,
            'municipio_id' => $position->municipio_id,
            'on_dispatch' => $shiftStatus === ShiftStatus::Empenhado,
        ];
    }

    /**
     * @param  Collection<string, TrackingPosition>  $liveByUnit
     * @return array<string, mixed>|null
     */
    private static function resolveMapVehicle(
        TrackingProvider $provider,
        Vehicle $vehicle,
        Collection $liveByUnit,
        CarbonInterface $at,
    ): ?array {
        $shiftStatus = self::activeShiftStatus((int) $vehicle->id, $at);

        if ($shiftStatus === null) {
            return null;
        }

        $stored = $vehicle->position;
        $reference = $provider->unitReferenceFor($vehicle);
        $live = $reference !== null ? $liveByUnit->get($reference) : null;

        if ($live !== null && self::shouldPreferLivePosition($stored, $live)) {
            self::persistLivePosition($vehicle, $live, $at);

            return self::formatLiveMapVehicle($vehicle, $live, $shiftStatus);
        }

        if ($stored !== null && self::hasValidCoordinates((float) $stored->latitude, (float) $stored->longitude)) {
            return self::formatStoredMapVehicle($vehicle, $stored, $shiftStatus);
        }

        if ($live !== null && self::hasValidCoordinates($live->latitude, $live->longitude)) {
            self::persistLivePosition($vehicle, $live, $at);

            return self::formatLiveMapVehicle($vehicle, $live, $shiftStatus);
        }

        return null;
    }

    private static function shouldPreferLivePosition(?VehiclePosition $stored, TrackingPosition $live): bool
    {
        if ($stored === null || $stored->fix_time === null) {
            return true;
        }

        if ($live->fixTime === '') {
            return false;
        }

        try {
            return Carbon::parse($live->fixTime)->gt($stored->fix_time);
        } catch (Throwable) {
            return false;
        }
    }

    /** @return array<string, mixed> */
    private static function formatStoredMapVehicle(
        Vehicle $vehicle,
        VehiclePosition $stored,
        ShiftStatus $shiftStatus,
    ): array {
        return [
            'vehicle_id' => $vehicle->id,
            'prefix' => $vehicle->prefix ?? $vehicle->plate ?? 'VTR',
            'lat' => (float) $stored->latitude,
            'lng' => (float) $stored->longitude,
            'speed_kmh' => (float) ($stored->speed_kmh ?? 0),
            'fix_time' => $stored->fix_time?->format('H:i:s'),
            'valid' => (bool) $stored->valid,
            'municipio_id' => $vehicle->municipio_id,
            'on_dispatch' => $shiftStatus === ShiftStatus::Empenhado,
        ];
    }

    /** @return array<string, mixed> */
    private static function formatLiveMapVehicle(
        Vehicle $vehicle,
        TrackingPosition $live,
        ShiftStatus $shiftStatus,
    ): array {
        return [
            'vehicle_id' => $vehicle->id,
            'prefix' => $vehicle->prefix ?? $vehicle->plate ?? 'VTR',
            'lat' => $live->latitude,
            'lng' => $live->longitude,
            'speed_kmh' => $live->speedKmh,
            'fix_time' => $live->fixTime !== ''
                ? Carbon::parse($live->fixTime)->format('H:i:s')
                : null,
            'valid' => $live->valid,
            'municipio_id' => $vehicle->municipio_id,
            'on_dispatch' => $shiftStatus === ShiftStatus::Empenhado,
        ];
    }

    private static function persistLivePosition(Vehicle $vehicle, TrackingPosition $live, CarbonInterface $at): void
    {
        VehiclePosition::query()->updateOrCreate(
            ['vehicle_id' => $vehicle->id],
            [
                'device_id' => $live->unitReference,
                'latitude' => $live->latitude,
                'longitude' => $live->longitude,
                'altitude' => $live->altitude ?: null,
                'speed_kmh' => $live->speedKmh,
                'course' => $live->course ?: null,
                'address' => $live->address !== '' ? $live->address : null,
                'valid' => $live->valid,
                'fix_time' => $live->fixTime !== '' ? Carbon::parse($live->fixTime) : null,
                'synced_at' => $at,
            ],
        );
    }

    private static function hasValidCoordinates(float $latitude, float $longitude): bool
    {
        return $latitude !== 0.0 || $longitude !== 0.0;
    }

    /**
     * Posições ao vivo do provedor de rastreamento ativo, com cache curto e
     * indexadas pela referência da unidade (device_id no Traccar,
     * ssx_integration_code no SSX).
     *
     * @return Collection<string, TrackingPosition>
     */
    private static function cachedLivePositions(TrackingProvider $provider): Collection
    {
        $cacheKey = self::livePositionsCacheKey();
        $cached = Cache::get($cacheKey);

        if (is_array($cached)) {
            return collect($cached)
                ->map(fn (array $payload): TrackingPosition => self::positionFromCache($payload))
                ->keyBy(fn (TrackingPosition $position): string => $position->unitReference);
        }

        if ($provider->circuitOpen()) {
            return collect();
        }

        try {
            // O provider já registra sucesso/falha no próprio circuit breaker.
            $positions = $provider->positions();
        } catch (Throwable) {
            return collect();
        }

        Cache::put(
            $cacheKey,
            $positions
                ->map(fn (TrackingPosition $position): array => [
                    'unitReference' => $position->unitReference,
                    'fixTime' => $position->fixTime,
                    'latitude' => $position->latitude,
                    'longitude' => $position->longitude,
                    'altitude' => $position->altitude,
                    'speedKmh' => $position->speedKmh,
                    'course' => $position->course,
                    'address' => $position->address,
                    'valid' => $position->valid,
                    'ignition' => $position->ignition,
                ])
                ->values()
                ->all(),
            now()->addSeconds(self::liveCacheTtlSeconds()),
        );

        return $positions->keyBy(fn (TrackingPosition $position): string => $position->unitReference);
    }

    /** @param array<string, mixed> $payload */
    private static function positionFromCache(array $payload): TrackingPosition
    {
        return new TrackingPosition(
            unitReference: (string) $payload['unitReference'],
            fixTime: (string) $payload['fixTime'],
            latitude: (float) $payload['latitude'],
            longitude: (float) $payload['longitude'],
            altitude: (float) $payload['altitude'],
            speedKmh: (float) $payload['speedKmh'],
            course: (float) $payload['course'],
            address: (string) $payload['address'],
            valid: (bool) $payload['valid'],
            ignition: (bool) ($payload['ignition'] ?? false),
        );
    }

    private static function livePositionsCacheKey(): string
    {
        return self::LIVE_POSITIONS_CACHE_PREFIX.'.'.(string) config('tracking.provider', 'traccar');
    }

    private static function liveCacheTtlSeconds(): int
    {
        $provider = (string) config('tracking.provider', 'traccar');

        return max(15, (int) ((int) config("{$provider}.positions_sync_interval", 60) / 2));
    }

    /** @param  Builder<Shift>  $query */
    private static function applyActiveShiftScope(Builder $query, CarbonInterface $at): Builder
    {
        return $query
            ->where('starts_at', '<=', $at)
            ->where('ends_at', '>=', $at)
            ->whereIn('status', self::mapVisibleShiftStatusValues());
    }

    /** @return list<string> */
    private static function mapVisibleShiftStatusValues(): array
    {
        return array_map(
            static fn (ShiftStatus $status): string => $status->value,
            self::mapVisibleShiftStatuses(),
        );
    }

    private static function resolveShiftStatus(mixed $status): ShiftStatus
    {
        if ($status instanceof ShiftStatus) {
            return $status;
        }

        return ShiftStatus::from((string) $status);
    }
}
