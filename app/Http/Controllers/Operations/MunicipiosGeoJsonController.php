<?php

declare(strict_types=1);

namespace App\Http\Controllers\Operations;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

/**
 * Retorna um GeoJSON FeatureCollection com os polígonos de todos os municípios
 * cadastrados que possuem geometria vinculada em ibge_municipios.
 *
 * Geometria simplificada (0.001 grau ≈ 111 m) para reduzir o payload
 * no nível de zoom usado no mapa de visão geral do dashboard.
 */
final class MunicipiosGeoJsonController extends Controller
{
    public function __invoke(): JsonResponse
    {
        // Agrupa por município IBGE para que o mesmo polígono não apareça
        // duplicado quando várias bases (municipios) estão na mesma cidade.
        $rows = DB::select("
            SELECT
                ST_AsGeoJSON(
                    ST_SimplifyPreserveTopology(im.geometry, 0.001)
                )                                                AS geojson,
                im.id                                            AS ibge_municipio_id,
                im.nome                                          AS nome,
                im.uf                                            AS uf,
                im.codigo_ibge                                   AS codigo_ibge,
                im.area_km2                                      AS area_km2,
                im.latitude                                      AS latitude,
                im.longitude                                     AS longitude,
                json_agg(
                    json_build_object(
                        'id',           m.id,
                        'razao_social', m.razao_social,
                        'city',         m.city,
                        'state',        m.state,
                        'phone',        m.phone,
                        'active',       m.active
                    )
                    ORDER BY m.razao_social
                )                                                AS bases,
                COUNT(m.id)::int                                 AS total_bases,
                BOOL_OR(m.active)                                AS any_active
            FROM ibge_municipios im
            INNER JOIN municipios m
                ON m.ibge_municipio_id = im.id
               AND m.deleted_at IS NULL
            WHERE im.geometry IS NOT NULL
            GROUP BY
                im.id, im.nome, im.uf, im.codigo_ibge,
                im.area_km2, im.latitude, im.longitude, im.geometry
            ORDER BY im.nome
        ");

        $features = [];

        foreach ($rows as $row) {
            if ($row->geojson === null) {
                continue;
            }

            $features[] = [
                'type' => 'Feature',
                'geometry' => json_decode($row->geojson),
                'properties' => [
                    'ibge_municipio_id' => $row->ibge_municipio_id,
                    'nome' => $row->nome,
                    'uf' => $row->uf,
                    'codigo_ibge' => $row->codigo_ibge,
                    'area_km2' => $row->area_km2,
                    'latitude' => $row->latitude,
                    'longitude' => $row->longitude,
                    'bases' => json_decode($row->bases, true),
                    'total_bases' => $row->total_bases,
                    'active' => (bool) $row->any_active,
                ],
            ];
        }

        return response()->json([
            'type' => 'FeatureCollection',
            'features' => $features,
        ]);
    }
}
