<?php

declare(strict_types=1);

namespace App\Http\Controllers\Operations;

use App\Http\Controllers\Controller;
use App\Models\IbgeMunicipio;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

final class IbgeMunicipioGeoJsonController extends Controller
{
    public function __invoke(IbgeMunicipio $ibgeMunicipio): JsonResponse
    {
        $row = DB::table('ibge_municipios')
            ->where('id', $ibgeMunicipio->id)
            ->selectRaw('ST_AsGeoJSON(geometry) as geojson')
            ->first();

        $geometry = ($row?->geojson !== null) ? json_decode($row->geojson) : null;

        return response()->json([
            'type' => 'Feature',
            'geometry' => $geometry,
            'properties' => [
                'nome' => $ibgeMunicipio->nome,
                'uf' => $ibgeMunicipio->uf,
                'codigo_ibge' => $ibgeMunicipio->codigo_ibge,
            ],
        ]);
    }
}
