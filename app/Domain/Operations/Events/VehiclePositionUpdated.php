<?php

declare(strict_types=1);

namespace App\Domain\Operations\Events;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

final class VehiclePositionUpdated implements ShouldBroadcastNow
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly int $vehicleId,
        public readonly int $municipioId,
        public readonly string $prefix,
        public readonly float $latitude,
        public readonly float $longitude,
        public readonly float $speedKmh,
        public readonly string $fixTime,
        public readonly bool $valid,
        public readonly bool $onDispatch,
    ) {}

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('operations.dispatch'),
            new PrivateChannel('operations.municipio.'.$this->municipioId),
        ];
    }

    public function broadcastAs(): string
    {
        return 'vehicle.position.updated';
    }

    /** @return array<string, mixed> */
    public function broadcastWith(): array
    {
        return [
            'vehicle_id' => $this->vehicleId,
            'municipio_id' => $this->municipioId,
            'prefix' => $this->prefix,
            'lat' => $this->latitude,
            'lng' => $this->longitude,
            'speed_kmh' => $this->speedKmh,
            'fix_time' => $this->fixTime,
            'valid' => $this->valid,
            'on_dispatch' => $this->onDispatch,
        ];
    }
}
