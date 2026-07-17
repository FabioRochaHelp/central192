<?php

declare(strict_types=1);

namespace App\Http\Controllers\Operations;

use App\Http\Controllers\Controller;
use App\Models\Incident;
use App\Support\Operations\NearestVehicleResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;
use Throwable;

final class IncidentNearestVehicleController extends Controller
{
    public function __invoke(Incident $incident): JsonResponse
    {
        Gate::authorize('view', $incident);

        try {
            $nearest = NearestVehicleResolver::for($incident);
        } catch (Throwable) {
            return response()->json([
                'nearest' => null,
                'error' => __('Não foi possível consultar as viaturas.'),
            ]);
        }

        return response()->json(['nearest' => $nearest]);
    }
}
