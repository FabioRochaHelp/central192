<?php

declare(strict_types=1);

namespace App\Integrations\Traccar;

use App\Integrations\Traccar\DTOs\TraccarDevice;
use App\Integrations\Traccar\DTOs\TraccarPosition;
use App\Integrations\Traccar\DTOs\TraccarRoutePoint;
use App\Integrations\Traccar\Exceptions\TraccarException;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

final class TraccarService
{
    public function __construct(private readonly TraccarClient $client) {}

    /** @return Collection<int, TraccarDevice> */
    public function devices(): Collection
    {
        return collect($this->client->devices())
            ->map(fn (array $d) => TraccarDevice::fromArray($d));
    }

    /** @return Collection<int, TraccarPosition> */
    public function positions(?int $deviceId = null): Collection
    {
        return collect($this->client->positions($deviceId))
            ->map(fn (array $p) => TraccarPosition::fromArray($p));
    }

    /**
     * Percurso histórico de um device num intervalo (replay da ocorrência).
     *
     * @return Collection<int, TraccarRoutePoint>
     */
    public function route(int $deviceId, CarbonInterface $from, CarbonInterface $to): Collection
    {
        [$fromUtc, $toUtc] = $this->normalizeInterval($from, $to);

        try {
            $points = $this->client->route($deviceId, $fromUtc, $toUtc);
        } catch (TraccarException $exception) {
            if ($exception->isUnauthorized() || $exception->isTimeout()) {
                throw $exception;
            }

            Log::info('Traccar route report failed, falling back to positions history', [
                'device_id' => $deviceId,
                'status' => $exception->statusCode,
            ]);

            $points = $this->client->positionsHistory($deviceId, $fromUtc, $toUtc);
        }

        if ($points === []) {
            $points = $this->client->positionsHistory($deviceId, $fromUtc, $toUtc);
        }

        return collect($points)
            ->map(fn (array $point) => TraccarRoutePoint::fromArray($point))
            ->sortBy('fixTime')
            ->values();
    }

    /** Verifica conectividade com o servidor Traccar. */
    public function ping(): bool
    {
        try {
            $info = $this->client->serverInfo();

            return isset($info['id']);
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function normalizeInterval(CarbonInterface $from, CarbonInterface $to): array
    {
        $fromInstant = Carbon::parse($from);
        $toInstant = Carbon::parse($to);

        if ($toInstant->lte($fromInstant)) {
            $toInstant = $fromInstant->copy()->addMinute();
        }

        return [
            TraccarClient::formatUtcInstant($fromInstant),
            TraccarClient::formatUtcInstant($toInstant),
        ];
    }
}
