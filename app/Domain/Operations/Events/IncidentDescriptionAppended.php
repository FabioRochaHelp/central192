<?php

declare(strict_types=1);

namespace App\Domain\Operations\Events;

use App\Models\Incident;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/** Acende o balão de anotações não vistas nos boards conectados, sem esperar o poll. */
final class IncidentDescriptionAppended implements ShouldBroadcast, ShouldDispatchAfterCommit
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
        return 'incident.description-appended';
    }

    /** @return array<string, mixed> */
    public function broadcastWith(): array
    {
        return [
            'incident_id' => $this->incident->id,
            'municipio_id' => $this->incident->municipio_id,
            'talao' => $this->incident->talao,
            'dispatch_year' => $this->incident->dispatch_year,
        ];
    }
}
