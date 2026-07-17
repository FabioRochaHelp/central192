<?php

declare(strict_types=1);

namespace App\Domain\Operations\Events;

use App\Models\OperationalCallAlert;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Disparado quando o webhook recebe `type=alert` — exibe marcador clicável no mapa tático.
 */
final class OperationalCallAlertReceived implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets;

    public function __construct(
        public OperationalCallAlert $alert,
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
        return 'operational.call-alert';
    }

    /** @return array<string, mixed> */
    public function broadcastWith(): array
    {
        return $this->alert->toMapPayload();
    }
}
