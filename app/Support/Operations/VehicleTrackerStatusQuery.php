<?php

declare(strict_types=1);

namespace App\Support\Operations;

use App\Integrations\Traccar\DTOs\TraccarDevice;
use App\Integrations\Traccar\TraccarCircuitBreaker;
use App\Integrations\Traccar\TraccarService;
use App\Integrations\Tracking\Contracts\TrackingProvider;
use App\Models\Vehicle;
use App\Models\VehiclePosition;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Throwable;

/** Status do rastreador Traccar vinculado à viatura (painel de despacho). */
final class VehicleTrackerStatusQuery
{
    public const string NoDevice = 'no_device';

    public const string Online = 'online';

    public const string Offline = 'offline';

    public const string Unknown = 'unknown';

    private const string CACHE_KEY = 'traccar.devices.snapshots_by_id';

    /**
     * @param  Collection<int, Vehicle>  $vehicles
     * @return array<int, string> vehicle_id => status
     */
    public static function forVehicles(Collection $vehicles, ?CarbonInterface $at = null): array
    {
        $at ??= now();

        $vehicles = $vehicles
            ->filter()
            ->unique('id')
            ->values();

        if ($vehicles->isEmpty()) {
            return [];
        }

        $provider = app(TrackingProvider::class);
        $deviceSnapshots = self::cachedDeviceSnapshots();

        $positions = $deviceSnapshots === null
            ? VehiclePosition::query()
                ->whereIn('vehicle_id', $vehicles->pluck('id'))
                ->get()
                ->keyBy('vehicle_id')
            : collect();

        $statuses = [];

        foreach ($vehicles as $vehicle) {
            $statuses[$vehicle->id] = self::resolveStatus(
                $provider,
                $vehicle,
                $deviceSnapshots,
                $positions->get($vehicle->id),
                $at,
            );
        }

        return $statuses;
    }

    public static function label(string $status): string
    {
        return match ($status) {
            self::Online => (string) __('Rastreador online'),
            self::Offline => (string) __('Rastreador offline'),
            self::NoDevice => (string) __('Sem rastreador vinculado'),
            default => (string) __('Status do rastreador indisponível'),
        };
    }

    /**
     * @param  Collection<int, array<string, mixed>>|null  $deviceSnapshots
     */
    private static function resolveStatus(
        TrackingProvider $provider,
        Vehicle $vehicle,
        ?Collection $deviceSnapshots,
        ?VehiclePosition $position,
        CarbonInterface $at,
    ): string {
        if ($provider->unitReferenceFor($vehicle) === null) {
            return self::NoDevice;
        }

        if ($deviceSnapshots !== null) {
            $deviceId = (int) $vehicle->device_id;

            /** @var array<string, mixed>|null $snapshot */
            $snapshot = $deviceSnapshots->get($deviceId);

            if ($snapshot === null) {
                return self::Unknown;
            }

            $device = TraccarDevice::fromArray($snapshot);

            // O Traccar mantém o estado da conexão do rastreador em `status`.
            // Uma viatura parada na base continua "online" mesmo sem um fix de GPS
            // recente (rastreadores reduzem o envio quando o veículo está parado),
            // então priorizamos o status e só recorremos à recência quando ele é
            // desconhecido/ausente.
            return match (strtolower(trim($device->status))) {
                'online' => self::Online,
                'offline' => self::Offline,
                default => $device->isReportingAt($at) ? self::Online : self::Unknown,
            };
        }

        if ($position === null) {
            return self::Unknown;
        }

        $fixTime = $position->fix_time ?? $position->synced_at;

        if ($fixTime === null) {
            return self::Offline;
        }

        $providerName = (string) config('tracking.provider', 'traccar');
        $thresholdSeconds = max(60, (int) config("{$providerName}.device_online_threshold_seconds", 180));

        return $fixTime->gte($at->copy()->subSeconds($thresholdSeconds))
            ? self::Online
            : self::Offline;
    }

    /**
     * Snapshot de status de devices — específico do Traccar. Outros provedores
     * (ex.: SSX) não expõem esse conceito, então retornam null e o status passa
     * a ser derivado da recência da última posição armazenada.
     *
     * @return Collection<int, array<string, mixed>>|null deviceId => device payload
     */
    private static function cachedDeviceSnapshots(): ?Collection
    {
        if ((string) config('tracking.provider', 'traccar') !== 'traccar') {
            return null;
        }

        $cached = Cache::get(self::CACHE_KEY);

        if (is_array($cached)) {
            return collect($cached);
        }

        if (TraccarCircuitBreaker::isOpen()) {
            return null;
        }

        try {
            $map = app(TraccarService::class)
                ->devices()
                ->mapWithKeys(fn (TraccarDevice $device): array => [
                    $device->id => [
                        'id' => $device->id,
                        'name' => $device->name,
                        'uniqueId' => $device->uniqueId,
                        'status' => $device->status,
                        'lastUpdate' => $device->lastUpdate,
                    ],
                ])
                ->all();

            TraccarCircuitBreaker::recordSuccess();

            Cache::put(
                self::CACHE_KEY,
                $map,
                now()->addSeconds(max(30, (int) config('traccar.positions_sync_interval', 60))),
            );

            return collect($map);
        } catch (Throwable) {
            TraccarCircuitBreaker::recordFailure();

            return null;
        }
    }
}
