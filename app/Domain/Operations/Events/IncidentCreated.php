<?php

declare(strict_types=1);

namespace App\Domain\Operations\Events;

use App\Models\Incident;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

final class IncidentCreated implements ShouldBroadcast, ShouldDispatchAfterCommit
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public Incident $incident,
    ) {}

    public function broadcastOn(): array
    {
        $channels = [
            new PrivateChannel('operations.dispatch'),
        ];

        if ($this->incident->municipio_id !== null) {
            $channels[] = new PrivateChannel('operations.municipio.'.$this->incident->municipio_id);
        }

        return $channels;
    }

    public function broadcastAs(): string
    {
        return 'incident.created';
    }

    /** @return array<string, mixed> */
    public function broadcastWith(): array
    {
        return [
            'incident_id' => $this->incident->id,
            'municipio_id' => $this->incident->municipio_id,
            'talao' => $this->incident->talao,
            'dispatch_year' => $this->incident->dispatch_year,
            'status' => $this->incident->status->value,
            'manchester_risk' => $this->incident->manchester_risk?->value,
            'lat' => $this->incident->latitude ? (float) $this->incident->latitude : null,
            'lng' => $this->incident->longitude ? (float) $this->incident->longitude : null,
            'nature' => $this->incident->nature?->name,
        ];
    }
}
