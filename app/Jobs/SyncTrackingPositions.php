<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Domain\Operations\Events\VehiclePositionUpdated;
use App\Integrations\Tracking\Contracts\TrackingProvider;
use App\Integrations\Tracking\DTOs\TrackingPosition;
use App\Models\Vehicle;
use App\Models\VehiclePosition;
use App\Support\Operations\TacticalMapVehicleQuery;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/** Sincroniza posições do provedor de rastreamento ativo (Traccar ou SSX). */
final class SyncTrackingPositions implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 30;

    public function handle(TrackingProvider $provider): void
    {
        if ($provider->circuitOpen()) {
            Log::info('SyncTrackingPositions ignorado — circuit breaker aberto');

            return;
        }

        $positions = $provider->positions();

        if ($positions->isEmpty()) {
            return;
        }

        $byReference = $positions->keyBy(fn (TrackingPosition $p): string => $p->unitReference);

        $vehicles = Vehicle::query()
            ->whereNotNull('municipio_id')
            ->get()
            ->filter(fn (Vehicle $vehicle): bool => $provider->unitReferenceFor($vehicle) !== null);

        if ($vehicles->isEmpty()) {
            return;
        }

        $now = now();
        $tobroadcast = [];

        DB::transaction(function () use ($provider, $vehicles, $byReference, $now, &$tobroadcast): void {
            foreach ($vehicles as $vehicle) {
                $reference = $provider->unitReferenceFor($vehicle);

                /** @var TrackingPosition|null $pos */
                $pos = $reference !== null ? $byReference->get($reference) : null;

                if ($pos === null) {
                    continue;
                }

                VehiclePosition::query()->updateOrCreate(
                    ['vehicle_id' => $vehicle->id],
                    [
                        'device_id' => $pos->unitReference,
                        'latitude' => $pos->latitude,
                        'longitude' => $pos->longitude,
                        'altitude' => $pos->altitude ?: null,
                        'speed_kmh' => $pos->speedKmh,
                        'course' => $pos->course ?: null,
                        'address' => $pos->address ?: null,
                        'valid' => $pos->valid,
                        'fix_time' => $pos->fixTime,
                        'synced_at' => $now,
                    ]
                );

                if (! TacticalMapVehicleQuery::vehicleIsOnMap($vehicle->id, $now)) {
                    continue;
                }

                $tobroadcast[] = new VehiclePositionUpdated(
                    vehicleId: $vehicle->id,
                    municipioId: (int) $vehicle->municipio_id,
                    prefix: (string) ($vehicle->prefix ?? $vehicle->plate ?? 'VTR'),
                    latitude: $pos->latitude,
                    longitude: $pos->longitude,
                    speedKmh: $pos->speedKmh,
                    fixTime: $pos->fixTime,
                    valid: $pos->valid,
                    onDispatch: TacticalMapVehicleQuery::vehicleIsOnDispatch($vehicle->id, $now),
                );
            }
        });

        foreach ($tobroadcast as $event) {
            event($event);
        }
    }

    public function failed(Throwable $e): void
    {
        Log::warning('SyncTrackingPositions falhou', ['error' => $e->getMessage()]);
    }
}
