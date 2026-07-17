<?php

declare(strict_types=1);

namespace App\Domain\Operations\Events;

use App\Models\Incident;
use App\Models\IncidentRegulation;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Médico regulador registrou a decisão de regulação. Quando autoriza envio de recurso,
 * a ocorrência passa a OPEN e cai na fila de despacho.
 */
final class RegulationDecided implements ShouldBroadcast, ShouldDispatchAfterCommit
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public Incident $incident,
        public IncidentRegulation $regulation,
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
        return 'regulation.decided';
    }

    /** @return array<string, mixed> */
    public function broadcastWith(): array
    {
        return [
            'incident_id' => $this->incident->id,
            'municipio_id' => $this->incident->municipio_id,
            'decision' => $this->regulation->status?->value,
            'status' => $this->incident->status->value,
        ];
    }
}
