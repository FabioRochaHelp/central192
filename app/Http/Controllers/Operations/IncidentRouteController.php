<?php

declare(strict_types=1);

namespace App\Http\Controllers\Operations;

use App\Domain\Operations\Actions\FetchIncidentRouteAction;
use App\Http\Controllers\Controller;
use App\Models\Incident;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;
use RuntimeException;
use Throwable;

final class IncidentRouteController extends Controller
{
    public function __invoke(Incident $incident, FetchIncidentRouteAction $action): JsonResponse
    {
        Gate::authorize('view', $incident);

        try {
            $points = $action->execute($incident);

            return response()->json([
                'points' => $points->map(fn ($p) => $p->toLeaflet())->values(),
                'count' => $points->count(),
            ]);
        } catch (RuntimeException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        } catch (Throwable) {
            return response()->json(['error' => __('Erro ao consultar rota no serviço de rastreamento.')], 500);
        }
    }
}
