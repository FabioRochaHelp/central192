<?php

declare(strict_types=1);

namespace App\Support\Operations;

use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

/** Busca de endereço via API pública Nominatim (OpenStreetMap). Respeite a política de uso (carga baixa, User-Agent identificado). */
final class OpenStreetMapGeocoder
{
    /**
     * @return array{
     *     lat: float,
     *     lon: float,
     *     display_name: string,
     *     street_line: string|null,
     *     district: string|null,
     *     city: string|null
     * }
     */
    public static function firstHit(string $query): array
    {
        $query = trim($query);
        if ($query === '') {
            throw new RuntimeException('empty_query');
        }

        $userAgent = trim((string) config('operations.osm_nominatim_user_agent', ''));
        if ($userAgent === '') {
            $userAgent = config('app.name').'/1.0 (SAMU — cadastro ocorrência)';
        }

        try {
            $response = Http::timeout(12)
                ->withHeaders([
                    'User-Agent' => $userAgent,
                    'Accept-Language' => app()->getLocale(),
                ])
                ->get('https://nominatim.openstreetmap.org/search', [
                    'q' => $query,
                    'format' => 'json',
                    'limit' => '1',
                    'addressdetails' => '1',
                ]);
        } catch (Throwable) {
            throw new RuntimeException('request_failed');
        }

        if (! $response->successful()) {
            throw new RuntimeException('http_error');
        }

        /** @var array<int, array<string, mixed>> $json */
        $json = $response->json();
        if ($json === null || $json === [] || ! isset($json[0]['lat'], $json[0]['lon'])) {
            throw new RuntimeException('no_results');
        }

        return self::parseNominatimPlace($json[0], $query);
    }

    /**
     * Geocodificação reversa (coordenadas → endereço legível).
     *
     * @return array{
     *     lat: float,
     *     lon: float,
     *     display_name: string,
     *     street_line: string|null,
     *     district: string|null,
     *     city: string|null
     * }
     */
    public static function reverseLookup(float $lat, float $lon): array
    {
        $userAgent = trim((string) config('operations.osm_nominatim_user_agent', ''));
        if ($userAgent === '') {
            $userAgent = config('app.name').'/1.0 (SAMU — cadastro ocorrência)';
        }

        try {
            $response = Http::timeout(12)
                ->withHeaders([
                    'User-Agent' => $userAgent,
                    'Accept-Language' => app()->getLocale(),
                ])
                ->get('https://nominatim.openstreetmap.org/reverse', [
                    'lat' => (string) $lat,
                    'lon' => (string) $lon,
                    'format' => 'json',
                    'addressdetails' => '1',
                ]);
        } catch (Throwable) {
            throw new RuntimeException('request_failed');
        }

        if (! $response->successful()) {
            throw new RuntimeException('http_error');
        }

        /** @var array<string, mixed>|null $row */
        $row = $response->json();
        if ($row === null || ! isset($row['lat'], $row['lon'])) {
            throw new RuntimeException('no_results');
        }

        return self::parseNominatimPlace($row, '');
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array{
     *     lat: float,
     *     lon: float,
     *     display_name: string,
     *     street_line: string|null,
     *     district: string|null,
     *     city: string|null
     * }
     */
    private static function parseNominatimPlace(array $row, string $fallbackDisplay): array
    {
        /** @var array<string, string>|null $addr */
        $addr = isset($row['address']) && is_array($row['address']) ? $row['address'] : null;

        $streetLine = null;
        $district = null;
        $city = null;

        if ($addr !== null) {
            $road = trim((string) ($addr['road'] ?? $addr['pedestrian'] ?? $addr['path'] ?? ''));
            $number = trim((string) ($addr['house_number'] ?? ''));
            $streetLine = trim($road.(($road !== '' && $number !== '') ? ', '.$number : ($number !== '' ? $number : '')));
            if ($streetLine === '') {
                $streetLine = null;
            }

            $district = $addr['suburb'] ?? $addr['neighbourhood'] ?? $addr['city_district'] ?? null;
            $district = $district !== null && $district !== '' ? (string) $district : null;

            $city = $addr['city'] ?? $addr['town'] ?? $addr['village'] ?? $addr['municipality'] ?? null;
            $city = $city !== null && $city !== '' ? (string) $city : null;
        }

        return [
            'lat' => (float) $row['lat'],
            'lon' => (float) $row['lon'],
            'display_name' => (string) ($row['display_name'] ?? $fallbackDisplay),
            'street_line' => $streetLine,
            'district' => $district,
            'city' => $city,
        ];
    }
}
