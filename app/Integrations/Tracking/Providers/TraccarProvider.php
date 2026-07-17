<?php

declare(strict_types=1);

namespace App\Integrations\Tracking\Providers;

use App\Integrations\Traccar\DTOs\TraccarPosition;
use App\Integrations\Traccar\DTOs\TraccarRoutePoint;
use App\Integrations\Traccar\Exceptions\TraccarException;
use App\Integrations\Traccar\TraccarCircuitBreaker;
use App\Integrations\Traccar\TraccarService;
use App\Integrations\Tracking\Contracts\TrackingProvider;
use App\Integrations\Tracking\DTOs\TrackingPosition;
use App\Integrations\Tracking\DTOs\TrackingRoutePoint;
use App\Integrations\Tracking\Exceptions\TrackingException;
use App\Models\Vehicle;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/** Adapter do Traccar para o contrato neutro TrackingProvider. */
final class TraccarProvider implements TrackingProvider
{
    public function __construct(private readonly TraccarService $traccar) {}

    public function healthy(): bool
    {
        return $this->traccar->ping();
    }

    public function circuitOpen(): bool
    {
        return TraccarCircuitBreaker::isOpen();
    }

    public function positions(): Collection
    {
        try {
            $positions = $this->traccar->positions();
            TraccarCircuitBreaker::recordSuccess();
        } catch (\Throwable $e) {
            TraccarCircuitBreaker::recordFailure();
            throw $e;
        }

        return $positions->map(fn (TraccarPosition $p): TrackingPosition => new TrackingPosition(
            unitReference: (string) $p->deviceId,
            fixTime: $p->fixTime,
            latitude: $p->latitude,
            longitude: $p->longitude,
            altitude: $p->altitude,
            speedKmh: $p->speedKmh(),
            course: $p->course,
            address: $p->address,
            valid: $p->valid,
        ))->values();
    }

    public function route(string $unitReference, CarbonInterface $from, CarbonInterface $to): Collection
    {
        try {
            $points = $this->traccar->route((int) $unitReference, $from, $to);
        } catch (TraccarException $e) {
            throw new TrackingException($e->getMessage(), $e->statusCode, $e, $e->responseBody);
        }

        return $points->map(fn (TraccarRoutePoint $point): TrackingRoutePoint => new TrackingRoutePoint(
            latitude: $point->latitude,
            longitude: $point->longitude,
            fixTime: $point->fixTime,
            speedKmh: round($point->speed * 1.852, 1),
        ))->values();
    }

    public function unitReferenceFor(Vehicle $vehicle): ?string
    {
        $deviceId = $vehicle->device_id;

        return ($deviceId === null || $deviceId === '') ? null : (string) $deviceId;
    }
}
