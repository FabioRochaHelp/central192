<?php

declare(strict_types=1);

namespace App\Http\Controllers\Operations;

use App\Http\Controllers\Controller;
use App\Support\Operations\OperationalMunicipioSelection;
use App\Support\Operations\TacticalMapVehicleQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

final class MapVehiclesController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $municipioId = OperationalMunicipioSelection::current(Auth::user());

        $vehicles = TacticalMapVehicleQuery::mapPayload($municipioId)
            ->map(fn (array $vehicle): array => [
                'vehicle_id' => $vehicle['vehicle_id'],
                'prefix' => $vehicle['prefix'],
                'lat' => $vehicle['lat'],
                'lng' => $vehicle['lng'],
                'speed_kmh' => $vehicle['speed_kmh'],
                'fix_time' => $vehicle['fix_time'],
                'valid' => $vehicle['valid'],
                'on_dispatch' => $vehicle['on_dispatch'],
            ])
            ->values();

        return response()->json($vehicles);
    }
}
