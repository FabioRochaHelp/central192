<?php

declare(strict_types=1);

namespace App\Support\Operations;

use App\Models\Incident;
use App\Models\OperationalCallAlert;
use Illuminate\Support\Collection;

/** Relatório de comportamento do fogo a partir dos metadados dos alertas no mesmo local. */
final class OperationalCallAlertFireReport
{
    /** @return array<string, mixed>|null */
    public static function fromLocationKey(string $locationKey): ?array
    {
        $alerts = OperationalCallAlertGrouper::activeAtLocationKey($locationKey);

        if ($alerts->isEmpty()) {
            return null;
        }

        return self::fromAlerts($locationKey, $alerts);
    }

    /** Relatório a partir dos alertas vinculados à ocorrência (status convertido). */
    public static function fromIncident(Incident $incident): ?array
    {
        $incident->loadMissing('operationalCallAlerts');

        $alerts = $incident->operationalCallAlerts
            ->sortBy(fn (OperationalCallAlert $alert) => $alert->call_received_at ?? $alert->created_at)
            ->values();

        if ($alerts->isEmpty()) {
            return null;
        }

        /** @var OperationalCallAlert $first */
        $first = $alerts->first();

        return self::fromAlerts(
            OperationalCallAlertGrouper::locationKey((float) $first->latitude, (float) $first->longitude),
            $alerts,
        );
    }

    /**
     * @param  Collection<int, OperationalCallAlert>  $alerts
     * @return array<string, mixed>
     */
    public static function fromAlerts(string $locationKey, Collection $alerts): array
    {
        /** @var OperationalCallAlert $latest */
        $latest = $alerts->sortByDesc(fn (OperationalCallAlert $a) => $a->call_received_at ?? $a->created_at)->first();
        /** @var OperationalCallAlert $earliest */
        $earliest = $alerts->sortBy(fn (OperationalCallAlert $a) => $a->call_received_at ?? $a->created_at)->first();

        $series = $alerts
            ->sortBy(fn (OperationalCallAlert $a) => $a->call_received_at ?? $a->created_at)
            ->values()
            ->map(fn (OperationalCallAlert $alert): array => self::snapshotFromAlert($alert))
            ->all();

        $latestMeta = self::metadataValues($latest);
        $earliestMeta = self::metadataValues($earliest);

        return [
            'location_key' => $locationKey,
            'lat' => (float) $latest->latitude,
            'lng' => (float) $latest->longitude,
            'alert_count' => $alerts->count(),
            'reference' => $latest->external_reference,
            'caller_name' => $latest->caller_name,
            'period' => [
                'from' => ($earliest->call_received_at ?? $earliest->created_at)?->timezone(config('app.timezone'))->format('d/m/Y H:i'),
                'to' => ($latest->call_received_at ?? $latest->created_at)?->timezone(config('app.timezone'))->format('d/m/Y H:i'),
            ],
            'latest' => $latestMeta,
            'metrics' => self::metricCards($latestMeta),
            'observations' => self::buildObservations($series, $earliestMeta, $latestMeta),
            'risk' => self::assessRisk($latestMeta, $series),
            'charts' => self::chartPayload($series),
            'wind_compass' => self::windCompass($latestMeta),
            'series' => $series,
        ];
    }

    /** @return array<string, float|string|null> */
    private static function metadataValues(OperationalCallAlert $alert): array
    {
        $metadata = is_array($alert->metadata) ? $alert->metadata : [];

        return [
            'temperature' => self::nullableFloat($metadata['temperature'] ?? null),
            'humidity' => self::nullableFloat($metadata['humidity'] ?? null),
            'wind_speed' => self::nullableFloat($metadata['wind_speed'] ?? null),
            'wind_direction' => self::nullableFloat($metadata['wind_direction'] ?? null),
            'wind_direction_text' => self::nullableString($metadata['wind_direction_text'] ?? null),
            'air_temperature' => self::nullableFloat($metadata['air_temperature'] ?? null),
            'rain' => self::nullableFloat($metadata['rain'] ?? null),
        ];
    }

    /** @return array<string, mixed> */
    private static function snapshotFromAlert(OperationalCallAlert $alert): array
    {
        $meta = self::metadataValues($alert);
        $receivedAt = $alert->call_received_at ?? $alert->created_at;

        return [
            'id' => $alert->id,
            'label' => $receivedAt?->timezone(config('app.timezone'))->format('d/m H:i') ?? '—',
            'received_at' => $receivedAt?->toIso8601String(),
            'reference' => $alert->external_reference,
            ...$meta,
        ];
    }

    /**
     * @param  array<string, float|string|null>  $meta
     * @return list<array{key: string, label: string, value: string, hint: string|null}>
     */
    private static function metricCards(array $meta): array
    {
        $cards = [];

        if ($meta['temperature'] !== null) {
            $cards[] = [
                'key' => 'temperature',
                'label' => __('Temperatura radiativa'),
                'value' => self::formatTemperature($meta['temperature']),
                'hint' => __('Brilho térmico detectado (sensor)'),
            ];
        }

        if ($meta['air_temperature'] !== null) {
            $cards[] = [
                'key' => 'air_temperature',
                'label' => __('Temperatura do ar'),
                'value' => number_format($meta['air_temperature'], 1, ',', '.').' °C',
                'hint' => null,
            ];
        }

        if ($meta['humidity'] !== null) {
            $cards[] = [
                'key' => 'humidity',
                'label' => __('Umidade relativa'),
                'value' => number_format($meta['humidity'], 1, ',', '.').' %',
                'hint' => null,
            ];
        }

        if ($meta['wind_speed'] !== null) {
            $cards[] = [
                'key' => 'wind_speed',
                'label' => __('Velocidade do vento'),
                'value' => number_format($meta['wind_speed'], 1, ',', '.').' km/h',
                'hint' => null,
            ];
        }

        if ($meta['wind_direction'] !== null || $meta['wind_direction_text'] !== null) {
            $direction = $meta['wind_direction_text'] ?? self::degreesToCardinal($meta['wind_direction']);
            $degrees = $meta['wind_direction'] !== null
                ? number_format($meta['wind_direction'], 0, ',', '.').'°'
                : null;

            $cards[] = [
                'key' => 'wind_direction',
                'label' => __('Direção do vento'),
                'value' => trim($direction.' '.($degrees ?? '')),
                'hint' => __('Sentido de propagação preferencial'),
            ];
        }

        if ($meta['rain'] !== null) {
            $cards[] = [
                'key' => 'rain',
                'label' => __('Precipitação'),
                'value' => number_format($meta['rain'], 1, ',', '.').' mm',
                'hint' => null,
            ];
        }

        return $cards;
    }

    /**
     * @param  list<array<string, mixed>>  $series
     * @param  array<string, float|string|null>  $earliest
     * @param  array<string, float|string|null>  $latest
     * @return list<array{level: string, title: string, text: string}>
     */
    private static function buildObservations(array $series, array $earliest, array $latest): array
    {
        $observations = [];

        if (count($series) >= 2) {
            $tempDelta = self::delta($earliest['temperature'], $latest['temperature']);
            if ($tempDelta !== null) {
                if ($tempDelta >= 5) {
                    $observations[] = [
                        'level' => 'high',
                        'title' => __('Aumento de temperatura radiativa'),
                        'text' => __('O brilho térmico subiu :delta entre a primeira e a última leitura, indicando intensificação do foco.', [
                            'delta' => self::formatTemperatureDelta($tempDelta),
                        ]),
                    ];
                } elseif ($tempDelta <= -5) {
                    $observations[] = [
                        'level' => 'info',
                        'title' => __('Redução de temperatura radiativa'),
                        'text' => __('O brilho térmico caiu :delta, possivelmente por enfraquecimento do foco ou mudança de sensor.', [
                            'delta' => self::formatTemperatureDelta(abs($tempDelta)),
                        ]),
                    ];
                }
            }

            $windDelta = self::angularDelta($earliest['wind_direction'], $latest['wind_direction']);
            if ($windDelta !== null && $windDelta >= 45) {
                $observations[] = [
                    'level' => 'medium',
                    'title' => __('Mudança na direção do vento'),
                    'text' => __('O vento mudou aproximadamente :degrees° entre leituras. A frente de propagação pode se deslocar.', [
                        'degrees' => number_format($windDelta, 0, ',', '.'),
                    ]),
                ];
            }

            $windSpeedDelta = self::delta($earliest['wind_speed'], $latest['wind_speed']);
            if ($windSpeedDelta !== null && $windSpeedDelta >= 10) {
                $observations[] = [
                    'level' => 'medium',
                    'title' => __('Vento mais intenso'),
                    'text' => __('A velocidade do vento aumentou :delta km/h, favorecendo propagação mais rápida.', [
                        'delta' => number_format($windSpeedDelta, 1, ',', '.'),
                    ]),
                ];
            }
        }

        if ($latest['humidity'] !== null && $latest['humidity'] < 30) {
            $observations[] = [
                'level' => 'high',
                'title' => __('Baixa umidade'),
                'text' => __('Umidade abaixo de 30% favorece combustão e reduz a eficácia de supressão.'),
            ];
        }

        if ($latest['wind_speed'] !== null && $latest['wind_speed'] >= 30) {
            $observations[] = [
                'level' => 'high',
                'title' => __('Vento forte'),
                'text' => __('Velocidade acima de 30 km/h aumenta risco de propagação lateral e spotting.'),
            ];
        }

        if ($latest['rain'] !== null && $latest['rain'] > 0) {
            $observations[] = [
                'level' => 'info',
                'title' => __('Precipitação registrada'),
                'text' => __('Chuva ou umidade precipitável pode reduzir a intensidade do foco.'),
            ];
        }

        if ($latest['air_temperature'] !== null && $latest['air_temperature'] >= 35
            && ($latest['humidity'] === null || $latest['humidity'] < 40)) {
            $observations[] = [
                'level' => 'medium',
                'title' => __('Condições adversas'),
                'text' => __('Temperatura ambiente elevada com umidade baixa aumenta a disponibilidade de combustível seco.'),
            ];
        }

        if ($observations === []) {
            $observations[] = [
                'level' => 'info',
                'title' => __('Leitura isolada'),
                'text' => count($series) === 1
                    ? __('Há apenas uma leitura neste local. Novos alertas permitirão analisar tendência e propagação.')
                    : __('Parâmetros estáveis entre as leituras disponíveis. Continue monitorando o local.'),
            ];
        }

        return $observations;
    }

    /**
     * @param  array<string, float|string|null>  $latest
     * @param  list<array<string, mixed>>  $series
     * @return array{level: string, label: string, score: int}
     */
    private static function assessRisk(array $latest, array $series): array
    {
        $score = 20;

        if ($latest['temperature'] !== null && $latest['temperature'] >= 330) {
            $score += 25;
        } elseif ($latest['temperature'] !== null && $latest['temperature'] >= 300) {
            $score += 15;
        }

        if ($latest['humidity'] !== null && $latest['humidity'] < 30) {
            $score += 20;
        } elseif ($latest['humidity'] !== null && $latest['humidity'] < 45) {
            $score += 10;
        }

        if ($latest['wind_speed'] !== null && $latest['wind_speed'] >= 30) {
            $score += 25;
        } elseif ($latest['wind_speed'] !== null && $latest['wind_speed'] >= 15) {
            $score += 12;
        }

        if ($latest['rain'] !== null && $latest['rain'] > 0) {
            $score -= 15;
        }

        if (count($series) >= 2) {
            $first = $series[0];
            $last = $series[count($series) - 1];
            $tempDelta = self::delta($first['temperature'] ?? null, $last['temperature'] ?? null);
            if ($tempDelta !== null && $tempDelta >= 5) {
                $score += 15;
            }
        }

        $score = max(0, min(100, $score));

        return match (true) {
            $score >= 75 => ['level' => 'critical', 'label' => __('Crítico'), 'score' => $score],
            $score >= 55 => ['level' => 'high', 'label' => __('Alto'), 'score' => $score],
            $score >= 35 => ['level' => 'moderate', 'label' => __('Moderado'), 'score' => $score],
            default => ['level' => 'low', 'label' => __('Baixo'), 'score' => $score],
        };
    }

    /**
     * @param  list<array<string, mixed>>  $series
     * @return array<string, mixed>
     */
    private static function chartPayload(array $series): array
    {
        $labels = array_column($series, 'label');

        return [
            'labels' => $labels,
            'temperature' => self::seriesValues($series, 'temperature'),
            'air_temperature' => self::seriesValues($series, 'air_temperature'),
            'humidity' => self::seriesValues($series, 'humidity'),
            'wind_speed' => self::seriesValues($series, 'wind_speed'),
            'rain' => self::seriesValues($series, 'rain'),
            'wind_direction' => self::seriesValues($series, 'wind_direction'),
        ];
    }

    /**
     * @param  array<string, float|string|null>  $meta
     * @return array{degrees: float|null, label: string|null, rotation: float}
     */
    private static function windCompass(array $meta): array
    {
        $degrees = $meta['wind_direction'];

        return [
            'degrees' => $degrees,
            'label' => $meta['wind_direction_text'] ?? ($degrees !== null ? self::degreesToCardinal($degrees) : null),
            'rotation' => $degrees ?? 0.0,
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $series
     * @return list<float|null>
     */
    private static function seriesValues(array $series, string $key): array
    {
        return array_map(
            fn (array $row): ?float => isset($row[$key]) && $row[$key] !== null ? (float) $row[$key] : null,
            $series,
        );
    }

    private static function nullableFloat(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (float) $value;
    }

    private static function nullableString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $string = trim((string) $value);

        return $string === '' ? null : $string;
    }

    private static function formatTemperature(float $value): string
    {
        if ($value >= 200) {
            return number_format($value, 1, ',', '.').' K ('.number_format($value - 273.15, 1, ',', '.').' °C)';
        }

        return number_format($value, 1, ',', '.').' °C';
    }

    private static function formatTemperatureDelta(float $delta): string
    {
        if ($delta >= 200) {
            return number_format($delta, 1, ',', '.').' K';
        }

        return number_format($delta, 1, ',', '.').' °C';
    }

    private static function delta(?float $from, ?float $to): ?float
    {
        if ($from === null || $to === null) {
            return null;
        }

        return $to - $from;
    }

    private static function angularDelta(?float $from, ?float $to): ?float
    {
        if ($from === null || $to === null) {
            return null;
        }

        $diff = abs($to - $from) % 360;

        return $diff > 180 ? 360 - $diff : $diff;
    }

    private static function degreesToCardinal(?float $degrees): string
    {
        if ($degrees === null) {
            return '—';
        }

        $directions = ['N', 'NE', 'E', 'SE', 'S', 'SW', 'W', 'NW'];
        $index = (int) round($degrees / 45) % 8;

        return $directions[$index];
    }
}
