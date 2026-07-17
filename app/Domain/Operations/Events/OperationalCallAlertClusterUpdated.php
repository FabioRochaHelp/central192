<?php

declare(strict_types=1);

namespace App\Domain\Operations\Events;

use App\Support\Operations\OperationalCallAlertGrouper;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

/** Atualiza marcador agrupado de alertas no mapa tático (contador / cor de monitoramento). */
final class OperationalCallAlertClusterUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets;

    /** @param  array<string, mixed>  $payload */
    public function __construct(
        public array $payload,
    ) {}

    public static function dispatchForLocation(float $lat, float $lng): void
    {
        $cluster = OperationalCallAlertGrouper::clusterForLocation($lat, $lng);
        $key = OperationalCallAlertGrouper::locationKey($lat, $lng);

        if ($cluster === null) {
            self::dispatch([
                'location_key' => $key,
                'remove' => true,
            ]);

            return;
        }

        self::dispatch($cluster);
    }

    /** @return array<int, PrivateChannel> */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('operations.dispatch'),
        ];
    }

    public function broadcastAs(): string
    {
        return 'operational.call-alert-cluster';
    }

    /** @return array<string, mixed> */
    public function broadcastWith(): array
    {
        return $this->payload;
    }
}
