<?php

declare(strict_types=1);

namespace App\Domain\Operations\Events;

use App\Models\Shift;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/** Disparado quando um turno é criado ou encerrado — atualiza o painel de viaturas em tempo real. */
final class ShiftUpdated implements ShouldBroadcastNow
{
    use Dispatchable, SerializesModels;

    public function __construct(public Shift $shift) {}

    public function broadcastOn(): array
    {
        $channels = [new PrivateChannel('operations.dispatch')];

        if ($this->shift->municipio_id !== null) {
            $channels[] = new PrivateChannel('operations.municipio.'.$this->shift->municipio_id);
        }

        return $channels;
    }

    public function broadcastAs(): string
    {
        return 'shift.updated';
    }

    /** @return array<string, mixed> */
    public function broadcastWith(): array
    {
        return [
            'shift_id' => $this->shift->id,
            'municipio_id' => $this->shift->municipio_id,
            'status' => $this->shift->status->value,
        ];
    }
}
