<?php

declare(strict_types=1);

namespace App\Support\Operations;

/** Distância geodésica (Haversine) compartilhada entre proximidade de viatura e de ocorrência. */
final class GeoDistance
{
    private const float EARTH_RADIUS_KM = 6371.0;

    private const float METERS_PER_DEGREE_LAT = 111_320.0;

    public static function haversineKm(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);

        $a = sin($dLat / 2) ** 2
            + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;

        return self::EARTH_RADIUS_KM * 2 * asin(min(1.0, sqrt($a)));
    }

    public static function haversineMeters(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        return self::haversineKm($lat1, $lng1, $lat2, $lng2) * 1000;
    }

    /**
     * Caixa envolvente para pré-filtrar candidatos por índice antes do Haversine.
     *
     * @return array{min_lat: float, max_lat: float, min_lng: float, max_lng: float}
     */
    public static function boundingBox(float $lat, float $lng, float $radiusMeters): array
    {
        $latDelta = $radiusMeters / self::METERS_PER_DEGREE_LAT;

        // Perto dos polos um grau de longitude encolhe; o piso evita divisão explosiva.
        $metersPerDegreeLng = self::METERS_PER_DEGREE_LAT * max(cos(deg2rad($lat)), 0.01);
        $lngDelta = $radiusMeters / $metersPerDegreeLng;

        return [
            'min_lat' => $lat - $latDelta,
            'max_lat' => $lat + $latDelta,
            'min_lng' => $lng - $lngDelta,
            'max_lng' => $lng + $lngDelta,
        ];
    }
}
