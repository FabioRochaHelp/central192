<?php

declare(strict_types=1);

namespace App\Support\Operations;

use App\Models\Incident;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Sugere a viatura mais próxima de uma ocorrência.
 *
 * Reaproveita {@see TacticalMapVehicleQuery::mapPayload()} (viaturas com turno vigente
 * + posição Traccar/persistida). A eleição da candidata em {@see self::for()} usa
 * Haversine (barato), enquanto a distância exibida na fila de empenho
 * ({@see self::distancesByVehicleId()}) é a real por vias via OSRM — o mesmo serviço
 * que o Leaflet Routing Machine consome no mapa operacional —, com fallback para
 * Haversine quando o roteador estiver indisponível.
 */
final class NearestVehicleResolver
{
    /** TTL curto: posições de viatura mudam, mas evita repetir a chamada OSRM no mesmo render. */
    private const int ROAD_CACHE_TTL_SECONDS = 30;

    /**
     * @return array<string, mixed>|null Payload da viatura mais próxima com `distance_km`, ou null.
     */
    public static function for(Incident $incident, ?int $municipioId = null): ?array
    {
        if ($incident->latitude === null || $incident->longitude === null) {
            return null;
        }

        $lat = (float) $incident->latitude;
        $lng = (float) $incident->longitude;

        $candidates = TacticalMapVehicleQuery::mapPayload($municipioId ?? $incident->municipio_id)
            ->filter(static fn (array $vehicle): bool => isset($vehicle['lat'], $vehicle['lng']))
            ->map(static function (array $vehicle) use ($lat, $lng): array {
                $vehicle['distance_km'] = round(
                    self::haversineKm($lat, $lng, (float) $vehicle['lat'], (float) $vehicle['lng']),
                    1,
                );

                return $vehicle;
            });

        if ($candidates->isEmpty()) {
            return null;
        }

        // Prioriza viatura disponível na base (on_dispatch = false); empata pela menor distância.
        return $candidates
            ->sort(static function (array $a, array $b): int {
                return [$a['on_dispatch'] ? 1 : 0, $a['distance_km']]
                    <=> [$b['on_dispatch'] ? 1 : 0, $b['distance_km']];
            })
            ->first();
    }

    /**
     * Distância real por vias (km) de cada viatura no mapa até a ocorrência.
     *
     * Usada tanto para ordenar a fila de empenho por proximidade quanto para exibir
     * a distância ao operador. Consulta o OSRM (serviço Table, uma origem → N destinos)
     * e cai para Haversine quando o roteador falha. Retorna coleção vazia quando a
     * ocorrência não tem coordenadas — o chamador deve então recorrer à fila justa.
     *
     * @return Collection<int, float> vehicle_id => distance_km
     */
    public static function distancesByVehicleId(Incident $incident, ?int $municipioId = null): Collection
    {
        if ($incident->latitude === null || $incident->longitude === null) {
            return collect();
        }

        $lat = (float) $incident->latitude;
        $lng = (float) $incident->longitude;

        $vehicles = TacticalMapVehicleQuery::mapPayload($municipioId ?? $incident->municipio_id)
            ->filter(static fn (array $vehicle): bool => isset($vehicle['vehicle_id'], $vehicle['lat'], $vehicle['lng']))
            ->values();

        if ($vehicles->isEmpty()) {
            return collect();
        }

        $straight = $vehicles->mapWithKeys(static fn (array $vehicle): array => [
            (int) $vehicle['vehicle_id'] => round(
                self::haversineKm($lat, $lng, (float) $vehicle['lat'], (float) $vehicle['lng']),
                1,
            ),
        ]);

        $road = self::roadDistancesByVehicleId($lat, $lng, $vehicles);

        // Mescla: usa a distância por vias quando disponível; senão mantém o Haversine.
        return $straight->map(static fn (float $km, int $vehicleId): float => $road[$vehicleId] ?? $km);
    }

    /**
     * Consulta o OSRM Table (uma origem → N destinos) e devolve a distância por vias em km.
     *
     * @param  Collection<int, array<string, mixed>>  $vehicles
     * @return array<int, float> vehicle_id => distance_km (apenas viaturas com rota encontrada)
     */
    private static function roadDistancesByVehicleId(float $lat, float $lng, Collection $vehicles): array
    {
        // Coordenadas no formato OSRM (lng,lat). Origem = ocorrência (índice 0), destinos = viaturas.
        $coordinates = $vehicles->map(
            static fn (array $vehicle): string => sprintf('%F,%F', (float) $vehicle['lng'], (float) $vehicle['lat']),
        );

        $path = sprintf('%F,%F;%s', $lng, $lat, $coordinates->implode(';'));
        $cacheKey = 'osrm:table:'.md5($path);

        return Cache::remember($cacheKey, self::ROAD_CACHE_TTL_SECONDS, static function () use ($path, $vehicles): array {
            $baseUrl = rtrim((string) config('services.osrm.url'), '/');

            try {
                $response = Http::timeout(4)
                    ->get("{$baseUrl}/table/v1/driving/{$path}", [
                        'sources' => '0',
                        'annotations' => 'distance',
                    ]);

                if (! $response->successful() || $response->json('code') !== 'Ok') {
                    return [];
                }

                // distances[0][j] = metros da origem até o destino j (j+1 no array global, pois 0 é a origem).
                $distances = $response->json('distances.0');

                if (! is_array($distances)) {
                    return [];
                }
            } catch (Throwable) {
                return [];
            }

            $result = [];

            foreach ($vehicles->values() as $index => $vehicle) {
                // +1 porque o índice 0 da resposta é a própria origem (ocorrência).
                $meters = $distances[$index + 1] ?? null;

                if (is_numeric($meters)) {
                    $result[(int) $vehicle['vehicle_id']] = round(((float) $meters) / 1000, 1);
                }
            }

            return $result;
        });
    }

    private static function haversineKm(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        return GeoDistance::haversineKm($lat1, $lng1, $lat2, $lng2);
    }
}
