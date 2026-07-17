<?php

declare(strict_types=1);

namespace App\Domain\Operations\Events;

use App\Models\Incident;
use App\Models\IncidentDispatch;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

final class UnitDispatched implements ShouldBroadcast, ShouldDispatchAfterCommit
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public Incident $incident,
        public IncidentDispatch $dispatch,
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
        return 'unit.dispatched';
    }

    /** @return array<string, mixed> */
    public function broadcastWith(): array
    {
        return [
            'incident_id' => $this->incident->id,
            'municipio_id' => $this->incident->municipio_id,
            'dispatch_id' => $this->dispatch->id,
            'shift_id' => $this->dispatch->shift_id,
            'stage' => $this->dispatch->stage->value,
            'status' => $this->incident->status->value,
            'lat' => $this->incident->latitude ? (float) $this->incident->latitude : null,
            'lng' => $this->incident->longitude ? (float) $this->incident->longitude : null,
        ];
    }
}
