<?php

declare(strict_types=1);

namespace App\Domain\Operations\Events;

use App\Models\Incident;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

final class UnitReleased implements ShouldBroadcast, ShouldDispatchAfterCommit
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public Incident $incident,
        public int $vehicleId,
    ) {}

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('operations.municipio.'.$this->incident->municipio_id),
            new PrivateChannel('operations.dispatch'),
            new PrivateChannel('incidents.'.$this->incident->id),
        ];
    }

    public function broadcastAs(): string
    {
        return 'unit.released';
    }

    /** @return array<string, mixed> */
    public function broadcastWith(): array
    {
        return [
            'incident_id' => $this->incident->id,
            'vehicle_id' => $this->vehicleId,
            'municipio_id' => $this->incident->municipio_id,
            'status' => $this->incident->status->value,
        ];
    }
}
