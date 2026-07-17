<?php

declare(strict_types=1);

namespace App\Domain\Operations\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

/** Remove marcador de alerta do mapa tático (abortado ou convertido em ocorrência). */
final class OperationalCallAlertAborted implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets;

    public function __construct(
        public string $alertId,
    ) {}

    /** @return array<int, PrivateChannel> */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('operations.dispatch'),
        ];
    }

    public function broadcastAs(): string
    {
        return 'operational.call-alert-aborted';
    }

    /** @return array<string, string> */
    public function broadcastWith(): array
    {
        return [
            'alert_id' => $this->alertId,
        ];
    }
}
