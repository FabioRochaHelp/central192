<?php

declare(strict_types=1);

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\Incident;
use App\Support\Fire\FireMapFocoQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;

final class MapFocosController extends Controller
{
    public function __invoke(Request $request, FireMapFocoQuery $query): JsonResponse
    {
        Gate::authorize('viewAny', Incident::class);

        $validated = $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
            'satelite' => ['nullable', 'string', 'max:50'],
        ]);

        return response()->json($query->forDashboard(
            Auth::user(),
            $validated['from'] ?? null,
            $validated['to'] ?? null,
            $validated['satelite'] ?? null,
        ));
    }
}
