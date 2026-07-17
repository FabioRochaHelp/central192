<?php

declare(strict_types=1);

namespace App\Integrations\Tracking\Providers\Ssx;

use App\Integrations\Tracking\DTOs\TrackingPosition;
use App\Integrations\Tracking\DTOs\TrackingRoutePoint;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * Traduz o domínio neutro de rastreamento para os filtros/respostas do SSX.
 *
 * Para "posição atual" usamos POST /Controlws/LastPosition/GetLastPositions
 * (uma linha por unidade, já a mais recente). O histórico
 * (POST /v3/Tracking/PositionHistory/List) é usado só para o percurso.
 */
final class SsxService
{
    public function __construct(private readonly SsxClient $client) {}

    public function ping(): bool
    {
        return $this->client->health();
    }

    /**
     * Última posição de cada unidade rastreada.
     *
     * @return Collection<int, TrackingPosition>
     */
    public function positions(): Collection
    {
        return collect($this->client->lastPositions())
            ->filter(fn (array $row): bool => ($row['TrackedUnitIntegrationCode'] ?? '') !== '')
            ->map(fn (array $row): TrackingPosition => $this->toPosition($row))
            ->values();
    }

    /**
     * Unidades rastreadas disponíveis para vínculo (seletor de cadastro).
     *
     * @return Collection<int, array{code: string, name: string, fixTime: string, valid: bool}>
     */
    public function units(): Collection
    {
        return collect($this->client->lastPositions())
            ->filter(fn (array $row): bool => ($row['TrackedUnitIntegrationCode'] ?? '') !== '')
            ->map(fn (array $row): array => [
                'code' => (string) $row['TrackedUnitIntegrationCode'],
                'name' => (string) ($row['TrackedUnit'] ?? '') !== ''
                    ? (string) $row['TrackedUnit']
                    : (string) $row['TrackedUnitIntegrationCode'],
                'fixTime' => (string) ($row['EventDate'] ?? ''),
                'valid' => (bool) ($row['ValidGPS'] ?? false),
            ])
            ->unique('code')
            ->sortBy('name', SORT_NATURAL | SORT_FLAG_CASE)
            ->values();
    }

    /**
     * Percurso histórico de uma unidade num intervalo.
     *
     * @return Collection<int, TrackingRoutePoint>
     */
    public function route(string $integrationCode, CarbonInterface $from, CarbonInterface $to): Collection
    {
        [$fromInstant, $toInstant] = $this->normalizeInterval($from, $to);

        $rows = $this->client->positionHistory([
            $this->condition('TrackedUnitIntegrationCode', '=', $integrationCode),
            $this->condition('EventDate', '>=', $this->formatInstant($fromInstant)),
            $this->condition('EventDate', '<=', $this->formatInstant($toInstant)),
        ]);

        return collect($rows)
            ->map(fn (array $row): TrackingRoutePoint => new TrackingRoutePoint(
                latitude: (float) ($row['Latitude'] ?? 0),
                longitude: (float) ($row['Longitude'] ?? 0),
                fixTime: (string) ($row['EventDate'] ?? ''),
                speedKmh: (float) ($row['Speed'] ?? 0),
            ))
            ->sortBy('fixTime')
            ->values();
    }

    /** @return array{PropertyName: string, Condition: string, Value: mixed} */
    private function condition(string $property, string $condition, mixed $value): array
    {
        return [
            'PropertyName' => $property,
            'Condition' => $condition,
            'Value' => $value,
        ];
    }

    /** @param array<string, mixed> $row */
    private function toPosition(array $row): TrackingPosition
    {
        return new TrackingPosition(
            unitReference: (string) $row['TrackedUnitIntegrationCode'],
            fixTime: (string) ($row['EventDate'] ?? ''),
            latitude: (float) ($row['Latitude'] ?? 0),
            longitude: (float) ($row['Longitude'] ?? 0),
            altitude: (float) ($row['Altitude'] ?? 0),
            speedKmh: (float) ($row['Speed'] ?? 0),
            course: (float) ($row['Course'] ?? 0),
            address: (string) ($row['Address'] ?? ''),
            valid: (bool) ($row['ValidGPS'] ?? false),
            ignition: (bool) ($row['Ignition'] ?? false),
        );
    }

    private function formatInstant(CarbonInterface $instant): string
    {
        $format = (string) config('ssx.date_format', 'Y-m-d\TH:i:s');

        return Carbon::parse($instant)->format($format);
    }

    /**
     * @return array{0: CarbonInterface, 1: CarbonInterface}
     */
    private function normalizeInterval(CarbonInterface $from, CarbonInterface $to): array
    {
        $fromInstant = Carbon::parse($from);
        $toInstant = Carbon::parse($to);

        if ($toInstant->lte($fromInstant)) {
            $toInstant = $fromInstant->copy()->addMinute();
        }

        return [$fromInstant, $toInstant];
    }
}
