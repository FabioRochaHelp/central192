<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Incident;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Busca condições meteorológicas históricas via Open-Meteo (gratuito, sem chave).
 *
 * Coordenadas do incidente têm prioridade; sem elas, geocodifica pelo nome da cidade
 * usando a API de geocodificação do próprio Open-Meteo.
 */
final class WeatherService
{
    /** @return array{temperature:int|null,humidity:int|null,wind_speed:int|null,wind_direction:string|null}|null */
    public function fetchForIncident(Incident $incident): ?array
    {
        [$lat, $lng] = $this->resolveCoordinates($incident);

        if ($lat === null || $lng === null) {
            return null;
        }

        $occurredAt = $incident->occurred_at;
        $date = $occurredAt->format('Y-m-d');
        $hour = (int) $occurredAt->format('H');

        // archive-api cobre de 1940 até ~5 dias atrás; forecast cobre presente e futuro próximo
        $baseUrl = $occurredAt->lt(now()->subDays(5))
            ? 'https://archive-api.open-meteo.com/v1/archive'
            : 'https://api.open-meteo.com/v1/forecast';

        try {
            $response = Http::timeout(8)->get($baseUrl, [
                'latitude' => $lat,
                'longitude' => $lng,
                'hourly' => 'temperature_2m,relative_humidity_2m,wind_speed_10m,wind_direction_10m',
                'wind_speed_unit' => 'kmh',
                'start_date' => $date,
                'end_date' => $date,
                'timezone' => 'auto',
            ]);

            if (! $response->successful()) {
                return null;
            }

            $hourly = $response->json('hourly');

            if (! is_array($hourly)) {
                return null;
            }

            return [
                'temperature' => isset($hourly['temperature_2m'][$hour])
                    ? (int) round((float) $hourly['temperature_2m'][$hour])
                    : null,
                'humidity' => isset($hourly['relative_humidity_2m'][$hour])
                    ? (int) round((float) $hourly['relative_humidity_2m'][$hour])
                    : null,
                'wind_speed' => isset($hourly['wind_speed_10m'][$hour])
                    ? (int) round((float) $hourly['wind_speed_10m'][$hour])
                    : null,
                'wind_direction' => $this->degreesToCompass($hourly['wind_direction_10m'][$hour] ?? null),
            ];
        } catch (\Throwable $e) {
            Log::warning('WeatherService: falha ao buscar dados meteorológicos', [
                'incident_id' => $incident->id,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /** @return array{0:float|null,1:float|null} */
    private function resolveCoordinates(Incident $incident): array
    {
        if ($incident->latitude !== null && $incident->longitude !== null) {
            return [(float) $incident->latitude, (float) $incident->longitude];
        }

        $query = $incident->city ?? $incident->address_line;

        if (! $query) {
            return [null, null];
        }

        try {
            $response = Http::timeout(5)->get('https://geocoding-api.open-meteo.com/v1/search', [
                'name' => $query,
                'count' => 1,
                'language' => 'pt',
                'format' => 'json',
                'countryCode' => 'BR',
            ]);

            if (! $response->successful()) {
                return [null, null];
            }

            $results = $response->json('results') ?? [];

            if (empty($results)) {
                return [null, null];
            }

            return [(float) $results[0]['latitude'], (float) $results[0]['longitude']];
        } catch (\Throwable) {
            return [null, null];
        }
    }

    private function degreesToCompass(mixed $degrees): ?string
    {
        if ($degrees === null) {
            return null;
        }

        $dirs = ['N', 'NE', 'L', 'SE', 'S', 'SO', 'O', 'NO'];

        return $dirs[(int) round((float) $degrees / 45) % 8];
    }
}
