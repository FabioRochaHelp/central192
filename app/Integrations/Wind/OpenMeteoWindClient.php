<?php

declare(strict_types=1);

namespace App\Integrations\Wind;

use App\Integrations\Wind\DTOs\WindReading;
use App\Integrations\Wind\Exceptions\WindException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/**
 * Cliente do Open-Meteo para vento a 10 m — API pública, sem chave.
 *
 * Todos os pontos vão numa única requisição (latitude/longitude aceitam lista
 * separada por vírgula), então o custo não cresce com o tamanho da grade.
 */
final class OpenMeteoWindClient
{
    /**
     * Vento atual em cada ponto pedido, na mesma ordem da entrada.
     *
     * @param  list<array{0: float, 1: float}>  $points  pares [lat, lng]
     * @return list<WindReading>
     *
     * @throws WindException
     */
    public function current(array $points): array
    {
        if ($points === []) {
            return [];
        }

        $path = trim((string) config('wind.forecast_path', 'v1/forecast'), '/');

        try {
            $response = $this->client()->get($path, [
                'latitude' => implode(',', array_map(static fn (array $p): string => (string) $p[0], $points)),
                'longitude' => implode(',', array_map(static fn (array $p): string => (string) $p[1], $points)),
                'current' => 'wind_speed_10m,wind_direction_10m,wind_gusts_10m',
            ]);
        } catch (ConnectionException $e) {
            throw new WindException('Wind service connection timed out', 408, $e);
        }

        $this->assertOk($response);

        $payload = $response->json();

        if (! is_array($payload)) {
            throw new WindException('Wind service returned an invalid payload', $response->status(), responseBody: $response->body());
        }

        return $this->parse($this->normalize($payload), $points);
    }

    /**
     * O Open-Meteo devolve um objeto quando há um único ponto e uma lista quando
     * há vários. Uniformiza para lista.
     *
     * @param  array<mixed>  $payload
     * @return list<array<string, mixed>>
     */
    private function normalize(array $payload): array
    {
        if (array_key_exists('latitude', $payload)) {
            return [$payload];
        }

        return array_values(array_filter($payload, is_array(...)));
    }

    /**
     * Casa cada resultado com o ponto pedido pela posição. As coordenadas da
     * resposta vêm ajustadas à grade do modelo (pedimos -22.12, volta -22.108963);
     * mantemos as pedidas para que as setas fiquem numa grade regular no mapa.
     *
     * @param  list<array<string, mixed>>  $results
     * @param  list<array{0: float, 1: float}>  $points
     * @return list<WindReading>
     */
    private function parse(array $results, array $points): array
    {
        $readings = [];

        foreach ($results as $index => $result) {
            $current = $result['current'] ?? null;
            $point = $points[$index] ?? null;

            if (! is_array($current) || $point === null) {
                continue;
            }

            if (! isset($current['wind_speed_10m'], $current['wind_direction_10m'])) {
                continue;
            }

            $readings[] = new WindReading(
                latitude: $point[0],
                longitude: $point[1],
                speedKmh: (float) $current['wind_speed_10m'],
                directionFromDeg: (float) $current['wind_direction_10m'],
                gustsKmh: isset($current['wind_gusts_10m']) ? (float) $current['wind_gusts_10m'] : null,
                observedAt: isset($current['time']) ? (string) $current['time'] : null,
            );
        }

        return $readings;
    }

    private function client(): PendingRequest
    {
        $baseUrl = (string) config('wind.base_url');

        if ($baseUrl === '') {
            throw new WindException('WIND_BASE_URL is not configured');
        }

        return Http::baseUrl($baseUrl)
            ->timeout((int) config('wind.timeout', 8))
            ->acceptJson();
    }

    private function assertOk(Response $response): void
    {
        if ($response->successful()) {
            return;
        }

        throw new WindException(
            sprintf('Wind service responded with HTTP %d', $response->status()),
            $response->status(),
            responseBody: $response->body(),
        );
    }
}
