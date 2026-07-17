<?php

declare(strict_types=1);

namespace App\Integrations\Wind;

use App\Integrations\Wind\DTOs\WindReading;
use App\Integrations\Wind\Exceptions\WindException;
use Illuminate\Support\Facades\Cache;

/**
 * Monta a grade de vento visível no mapa.
 *
 * O vento é um campo espacialmente suave: em vez de uma leitura por marcador,
 * amostramos numa grade fixa e cacheamos por célula. Como as células são
 * ancoradas em múltiplos do passo (e não no viewport), panorâmicas vizinhas
 * reaproveitam o cache em vez de gerar chamadas novas.
 */
final class WindService
{
    private const CACHE_PREFIX = 'wind:v1';

    public function __construct(private readonly OpenMeteoWindClient $client) {}

    /**
     * Leituras cobrindo a área visível. Degrada em silêncio: se o provedor falhar,
     * devolve o que houver em cache — vento é contexto, não pode derrubar o mapa.
     *
     * @return list<WindReading>
     */
    public function forBounds(float $minLat, float $minLng, float $maxLat, float $maxLng): array
    {
        $cells = $this->resolveCells($minLat, $minLng, $maxLat, $maxLng);

        $readings = [];
        $missing = [];

        foreach ($cells as $cell) {
            $cached = Cache::get($this->cacheKey($cell[0], $cell[1]));

            if (is_array($cached)) {
                $readings[] = WindReading::fromArray($cached);

                continue;
            }

            $missing[] = $cell;
        }

        if ($missing === []) {
            return $readings;
        }

        try {
            $fetched = $this->client->current($missing);
        } catch (WindException) {
            return $readings;
        }

        $ttl = now()->addMinutes((int) config('wind.cache_ttl_minutes', 15));

        foreach ($fetched as $reading) {
            Cache::put($this->cacheKey($reading->latitude, $reading->longitude), $reading->toArray(), $ttl);
            $readings[] = $reading;
        }

        return $readings;
    }

    /**
     * Escolhe a resolução mais fina cujo número de células caiba no teto e devolve
     * as células correspondentes. Sem isso, um mapa afastado pediria centenas de
     * pontos; com isso, o custo por requisição é limitado em qualquer zoom.
     *
     * @return list<array{0: float, 1: float}>
     */
    private function resolveCells(float $minLat, float $minLng, float $maxLat, float $maxLng): array
    {
        $maxCells = max(1, (int) config('wind.grid.max_cells', 24));
        /** @var list<float> $steps */
        $steps = config('wind.grid.steps', [0.25, 0.5, 1.0, 2.0]);

        $cells = [];

        foreach ($steps as $step) {
            $cells = $this->cellsFor($minLat, $minLng, $maxLat, $maxLng, (float) $step);

            if (count($cells) <= $maxCells) {
                return $cells;
            }
        }

        // Nem o passo mais grosso coube (área enorme): corta no teto.
        return array_slice($cells, 0, $maxCells);
    }

    /**
     * Células ancoradas em múltiplos do passo — a âncora global é o que torna o
     * cache reaproveitável entre viewports diferentes.
     *
     * @return list<array{0: float, 1: float}>
     */
    private function cellsFor(float $minLat, float $minLng, float $maxLat, float $maxLng, float $step): array
    {
        $startLat = floor($minLat / $step) * $step;
        $startLng = floor($minLng / $step) * $step;

        // Passos inteiros evitam o acúmulo de erro de ponto flutuante do `+=`.
        $latCount = (int) floor(($maxLat - $startLat) / $step);
        $lngCount = (int) floor(($maxLng - $startLng) / $step);

        $cells = [];

        for ($i = 0; $i <= $latCount; $i++) {
            for ($j = 0; $j <= $lngCount; $j++) {
                $cells[] = [
                    round($startLat + ($i * $step), 4),
                    round($startLng + ($j * $step), 4),
                ];
            }
        }

        return $cells;
    }

    private function cacheKey(float $lat, float $lng): string
    {
        return sprintf('%s:%.4f:%.4f', self::CACHE_PREFIX, $lat, $lng);
    }
}
