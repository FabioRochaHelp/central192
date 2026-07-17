<?php

declare(strict_types=1);

namespace App\Domain\Operations\Events;

use App\Models\Incident;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/** Disparado quando uma ocorrência atinge estado terminal (encerrada/cancelada) — sai das filas operacionais. */
final class IncidentFinalized implements ShouldBroadcastNow
{
    use Dispatchable, SerializesModels;

    public function __construct(public Incident $incident) {}

    public function broadcastOn(): array
    {
        $channels = [
            new PrivateChannel('operations.dispatch'),
            new PrivateChannel('incidents.'.$this->incident->id),
        ];

        if ($this->incident->municipio_id !== null) {
            $channels[] = new PrivateChannel('operations.municipio.'.$this->incident->municipio_id);
        }

        return $channels;
    }

    public function broadcastAs(): string
    {
        return 'incident.finalized';
    }

    /** @return array<string, mixed> */
    public function broadcastWith(): array
    {
        return [
            'incident_id' => $this->incident->id,
            'municipio_id' => $this->incident->municipio_id,
            'status' => $this->incident->status->value,
        ];
    }
}
