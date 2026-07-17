<?php

declare(strict_types=1);

namespace App\Domain\Operations\Events;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/** Avisa clientes Echo para recalcular contagens do painel (escopo continua no servidor). */
final class DashboardCallStatsInvalidate implements ShouldBroadcast
{
    use Dispatchable, SerializesModels;

    /** @return array<int, PrivateChannel> */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('operations.dispatch'),
        ];
    }

    public function broadcastAs(): string
    {
        return 'dashboard.call-stats-invalidate';
    }

    /** @return array<string, mixed> */
    public function broadcastWith(): array
    {
        return [];
    }
}
