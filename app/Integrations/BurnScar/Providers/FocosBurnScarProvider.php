<?php

declare(strict_types=1);

namespace App\Integrations\BurnScar\Providers;

use App\Integrations\BurnScar\Contracts\BurnScarProvider;
use App\Integrations\BurnScar\DTOs\BurnScarResult;
use App\Integrations\BurnScar\Exceptions\BurnScarException;
use App\Models\FireScarAnalysis;
use App\Models\FocoSatelite;
use Carbon\CarbonInterface;
use Illuminate\Database\QueryException;
use PDOException;
use Throwable;

/**
 * Estimador LOCAL de cicatriz a partir dos focos de calor (INPE) já importados.
 *
 * Agrupa os focos próximos à coordenada dentro da janela de fogo, monta um polígono
 * com buffer (~footprint do pixel) e estima área e perímetro. NÃO calcula índices
 * espectrais (NBR/dNBR) nem severidade — apenas o "footprint" da área queimada.
 */
final class FocosBurnScarProvider implements BurnScarProvider
{
    private const EARTH_M_PER_DEG_LAT = 111_320.0;

    private const CIRCLE_SEGMENTS = 16;

    public function analyze(FireScarAnalysis $analysis): BurnScarResult
    {
        $lat = (float) $analysis->latitude;
        $lon = (float) $analysis->longitude;
        $radiusKm = (float) config('burnscar.focos.radius_km', 15);
        $bufferM = (float) config('burnscar.focos.buffer_m', 500);

        [$start, $end] = $this->resolveWindow($analysis);

        try {
            $focos = $this->collectFocos($lat, $lon, $radiusKm, $start, $end);
        } catch (QueryException|PDOException $e) {
            throw new BurnScarException('Não foi possível consultar os focos de calor: '.$e->getMessage(), previous: $e);
        }

        $count = count($focos);

        $notesBase = sprintf(
            'Cicatriz estimada localmente a partir de %d foco(s) de calor (INPE) num raio de %s km e janela %s a %s; buffer de %s m por foco. Área/perímetro aproximados — sem severidade espectral.',
            $count,
            rtrim(rtrim(number_format($radiusKm, 1, '.', ''), '0'), '.'),
            $start->toDateString(),
            $end->toDateString(),
            rtrim(rtrim(number_format($bufferM, 0), '0'), '.'),
        );

        if ($count === 0) {
            return $this->emptyResult('Nenhum foco de calor encontrado no raio/janela informados. '.$notesBase);
        }

        try {
            $polygon = $this->bufferedPolygon($focos, $lat, $lon, $bufferM);
        } catch (Throwable $e) {
            throw new BurnScarException('Falha ao montar o polígono da cicatriz: '.$e->getMessage(), previous: $e);
        }

        $areaM2 = $this->polygonAreaM2($polygon['projected']);
        $perimeterM = $this->polygonPerimeterM($polygon['projected']);

        return new BurnScarResult(
            nbrPre: null,
            nbrPost: null,
            dnbr: null,
            rbr: null,
            ndviPre: null,
            ndviPost: null,
            bai: null,
            severityClass: null,
            dnbrRange: null,
            confidence: $count >= 8 ? 'média' : 'baixa',
            areaHa: round($areaM2 / 10_000, 2),
            perimeterKm: round($perimeterM / 1_000, 2),
            geometryGeojson: [
                'type' => 'Polygon',
                'coordinates' => [$polygon['geographic']],
            ],
            vegetationType: null,
            notes: $notesBase,
            externalRef: 'focos:'.$count,
        );
    }

    public function name(): string
    {
        return 'focos';
    }

    /**
     * @return list<array{lat:float,lon:float}>
     */
    private function collectFocos(float $lat, float $lon, float $radiusKm, CarbonInterface $start, CarbonInterface $end): array
    {
        $dLat = $radiusKm / 111.32;
        $cos = max(cos(deg2rad($lat)), 0.01);
        $dLon = $radiusKm / (111.32 * $cos);

        $rows = FocoSatelite::query()
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->whereBetween('latitude', [$lat - $dLat, $lat + $dLat])
            ->whereBetween('longitude', [$lon - $dLon, $lon + $dLon])
            ->whereRaw('COALESCE(data_hora_local, data_hora_gmt, data_insercao) >= ?', [$start])
            ->whereRaw('COALESCE(data_hora_local, data_hora_gmt, data_insercao) <= ?', [$end])
            ->limit(5000)
            ->get(['latitude', 'longitude']);

        $out = [];

        foreach ($rows as $row) {
            $flat = (float) $row->latitude;
            $flon = (float) $row->longitude;

            // Filtro fino por distância real (haversine) dentro do raio.
            if ($this->haversineKm($lat, $lon, $flat, $flon) <= $radiusKm) {
                $out[] = ['lat' => $flat, 'lon' => $flon];
            }
        }

        return $out;
    }

    /**
     * Monta o polígono com buffer: gera pontos de círculo (raio = buffer) ao redor de
     * cada foco e toma o convex hull — resultado consistente para 1, 2 ou N focos.
     *
     * @param  list<array{lat:float,lon:float}>  $focos
     * @return array{projected:list<array{0:float,1:float}>,geographic:list<array{0:float,1:float}>}
     */
    private function bufferedPolygon(array $focos, float $lat0, float $lon0, float $bufferM): array
    {
        $mPerDegLon = self::EARTH_M_PER_DEG_LAT * max(cos(deg2rad($lat0)), 0.01);

        $points = [];
        foreach ($focos as $foco) {
            $cx = ($foco['lon'] - $lon0) * $mPerDegLon;
            $cy = ($foco['lat'] - $lat0) * self::EARTH_M_PER_DEG_LAT;

            for ($i = 0; $i < self::CIRCLE_SEGMENTS; $i++) {
                $angle = 2 * M_PI * $i / self::CIRCLE_SEGMENTS;
                $points[] = [$cx + $bufferM * cos($angle), $cy + $bufferM * sin($angle)];
            }
        }

        $hull = $this->convexHull($points);

        $geographic = [];
        foreach ($hull as [$x, $y]) {
            $geographic[] = [
                round($lon0 + $x / $mPerDegLon, 6),
                round($lat0 + $y / self::EARTH_M_PER_DEG_LAT, 6),
            ];
        }

        // Fecha o anel do GeoJSON.
        if ($geographic !== [] && $geographic[0] !== end($geographic)) {
            $geographic[] = $geographic[0];
        }

        return ['projected' => $hull, 'geographic' => $geographic];
    }

    /**
     * Convex hull (Andrew's monotone chain).
     *
     * @param  list<array{0:float,1:float}>  $points
     * @return list<array{0:float,1:float}>
     */
    private function convexHull(array $points): array
    {
        $points = array_values(array_unique(array_map(
            static fn (array $p): string => $p[0].','.$p[1],
            $points,
        )));
        $points = array_map(static function (string $key): array {
            [$x, $y] = explode(',', $key);

            return [(float) $x, (float) $y];
        }, $points);

        $n = count($points);
        if ($n < 3) {
            return $points;
        }

        usort($points, static fn (array $a, array $b): int => $a[0] <=> $b[0] ?: $a[1] <=> $b[1]);

        $cross = static fn (array $o, array $a, array $b): float => ($a[0] - $o[0]) * ($b[1] - $o[1]) - ($a[1] - $o[1]) * ($b[0] - $o[0]);

        $lower = [];
        foreach ($points as $p) {
            while (count($lower) >= 2 && $cross($lower[count($lower) - 2], $lower[count($lower) - 1], $p) <= 0) {
                array_pop($lower);
            }
            $lower[] = $p;
        }

        $upper = [];
        foreach (array_reverse($points) as $p) {
            while (count($upper) >= 2 && $cross($upper[count($upper) - 2], $upper[count($upper) - 1], $p) <= 0) {
                array_pop($upper);
            }
            $upper[] = $p;
        }

        array_pop($lower);
        array_pop($upper);

        return array_merge($lower, $upper);
    }

    /** @param list<array{0:float,1:float}> $ring */
    private function polygonAreaM2(array $ring): float
    {
        $n = count($ring);
        if ($n < 3) {
            return 0.0;
        }

        $sum = 0.0;
        for ($i = 0; $i < $n; $i++) {
            $j = ($i + 1) % $n;
            $sum += $ring[$i][0] * $ring[$j][1] - $ring[$j][0] * $ring[$i][1];
        }

        return abs($sum) / 2;
    }

    /** @param list<array{0:float,1:float}> $ring */
    private function polygonPerimeterM(array $ring): float
    {
        $n = count($ring);
        if ($n < 2) {
            return 0.0;
        }

        $sum = 0.0;
        for ($i = 0; $i < $n; $i++) {
            $j = ($i + 1) % $n;
            $sum += hypot($ring[$j][0] - $ring[$i][0], $ring[$j][1] - $ring[$i][1]);
        }

        return $sum;
    }

    private function haversineKm(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        $r = 6371.0;
        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);
        $a = sin($dLat / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLon / 2) ** 2;

        return $r * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }

    /** @return array{0:CarbonInterface,1:CarbonInterface} */
    private function resolveWindow(FireScarAnalysis $analysis): array
    {
        $windowDays = (int) config('burnscar.focos.window_days', 10);

        if ($analysis->pre_fire_date !== null && $analysis->post_fire_date !== null) {
            return [
                $analysis->pre_fire_date->copy()->startOfDay(),
                $analysis->post_fire_date->copy()->endOfDay(),
            ];
        }

        $center = $analysis->post_fire_date ?? $analysis->requested_at ?? now();

        return [
            $center->copy()->subDays($windowDays)->startOfDay(),
            $center->copy()->addDays($windowDays)->endOfDay(),
        ];
    }

    private function emptyResult(string $notes): BurnScarResult
    {
        return new BurnScarResult(
            nbrPre: null, nbrPost: null, dnbr: null, rbr: null,
            ndviPre: null, ndviPost: null, bai: null,
            severityClass: null, dnbrRange: null, confidence: 'baixa',
            areaHa: null, perimeterKm: null, geometryGeojson: null,
            vegetationType: null, notes: $notes, externalRef: 'focos:0',
        );
    }
}
