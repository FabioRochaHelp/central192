<?php

declare(strict_types=1);

namespace App\Support\Fire;

use App\Models\FocoSatelite;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

final class FireMapFocoQuery
{
    /**
     * @return Collection<int, array{
     *     id: int,
     *     lat: float,
     *     lng: float,
     *     municipio: ?string,
     *     estado: ?string,
     *     satelite: ?string,
     *     sensor: ?string,
     *     bioma: ?string,
     *     frp: ?float,
     *     temperatura_brilho: ?float,
     *     risco_fogo: ?float,
     *     confianca: ?int,
     *     detected_at: ?string
     * }>
     */
    public function forDashboard(
        ?User $user,
        ?string $from = null,
        ?string $to = null,
        ?string $sateliteId = null,
    ): Collection {
        [$start, $end] = $this->resolvePeriod($from, $to);

        $query = FocoSatelite::query()
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->whereRaw('COALESCE(data_hora_local, data_hora_gmt, data_insercao) >= ?', [$start])
            ->whereRaw('COALESCE(data_hora_local, data_hora_gmt, data_insercao) <= ?', [$end]);

        // Padroniza a região do consórcio (SP) por coordenada — ver FocosBoundingBox.
        FocosBoundingBox::apply($query);

        if ($sateliteId !== null && $sateliteId !== '') {
            $query->where('satelite_id', $sateliteId);
        }

        return $query
            ->orderByDesc('data_hora_local')
            ->orderByDesc('data_hora_gmt')
            ->limit((int) config('fire.dashboard_map_limit', 500))
            ->get()
            ->map(fn (FocoSatelite $registro): array => [
                'id' => $registro->id,
                'lat' => (float) $registro->latitude,
                'lng' => (float) $registro->longitude,
                'municipio' => $registro->municipio,
                'estado' => $registro->estado,
                'satelite' => $registro->satelite_id,
                'sensor' => $registro->sensor,
                'bioma' => $registro->bioma,
                'frp' => $registro->frp !== null ? (float) $registro->frp : null,
                'temperatura_brilho' => $registro->temperatura_brilho !== null ? (float) $registro->temperatura_brilho : null,
                'risco_fogo' => $registro->risco_fogo_inpe !== null ? (float) $registro->risco_fogo_inpe : null,
                'confianca' => $registro->confianca,
                'detected_at' => $this->formatDetectedAt($registro),
            ])
            ->values();
    }

    /**
     * @return Collection<int, string>
     */
    public function availableSatellites(): Collection
    {
        return FocoSatelite::query()
            ->whereNotNull('satelite_id')
            ->distinct()
            ->orderBy('satelite_id')
            ->pluck('satelite_id');
    }

    private function formatDetectedAt(FocoSatelite $registro): ?string
    {
        $detectedAt = $registro->data_hora_local ?? $registro->data_hora_gmt;

        if ($detectedAt === null) {
            return null;
        }

        return Carbon::parse($detectedAt)
            ->timezone(config('app.timezone'))
            ->format('d/m/Y H:i');
    }

    /**
     * @return array{0: Carbon, 1: Carbon}
     */
    private function resolvePeriod(?string $from, ?string $to): array
    {
        if ($from !== null && $to !== null) {
            return [
                Carbon::parse($from)->startOfDay(),
                Carbon::parse($to)->endOfDay(),
            ];
        }

        return [
            now()->subDays((int) config('fire.dashboard_map_days', 60))->startOfDay(),
            now()->endOfDay(),
        ];
    }
}
