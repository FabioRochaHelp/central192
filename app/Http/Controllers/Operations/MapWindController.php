<?php

declare(strict_types=1);

namespace App\Http\Controllers\Operations;

use App\Http\Controllers\Controller;
use App\Integrations\Wind\DTOs\WindReading;
use App\Integrations\Wind\WindService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Grade de vento atual para a área visível do mapa operacional. */
final class MapWindController extends Controller
{
    public function __invoke(Request $request, WindService $wind): JsonResponse
    {
        $validated = $request->validate([
            'min_lat' => ['required', 'numeric', 'between:-90,90'],
            'max_lat' => ['required', 'numeric', 'between:-90,90', 'gte:min_lat'],
            'min_lng' => ['required', 'numeric', 'between:-180,180'],
            'max_lng' => ['required', 'numeric', 'between:-180,180', 'gte:min_lng'],
        ]);

        $readings = $wind->forBounds(
            (float) $validated['min_lat'],
            (float) $validated['min_lng'],
            (float) $validated['max_lat'],
            (float) $validated['max_lng'],
        );

        return response()->json(
            array_map(static fn (WindReading $reading): array => $reading->toLeaflet(), $readings)
        );
    }
}
