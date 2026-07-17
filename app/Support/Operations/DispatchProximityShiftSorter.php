<?php

declare(strict_types=1);

namespace App\Support\Operations;

use App\Models\Incident;
use App\Models\Shift;
use Illuminate\Support\Collection;

/**
 * Ordena a fila de empenho pela viatura mais próxima da ocorrência.
 *
 * Substitui a fila justa ("mais tempo na base") quando a ocorrência tem coordenadas
 * e há posições de viatura disponíveis. Mantém {@see DispatchFairQueueShiftSorter}
 * como base estável: empates de distância e viaturas sem posição preservam a ordem
 * da fila justa, e o fallback completo é acionado quando não há distâncias.
 */
final class DispatchProximityShiftSorter
{
    /**
     * @param  Collection<int, Shift>  $shifts
     * @return Collection<int, Shift>
     */
    public static function sort(Collection $shifts, Incident $incident): Collection
    {
        $distances = NearestVehicleResolver::distancesByVehicleId($incident);

        $fairOrdered = DispatchFairQueueShiftSorter::sort($shifts);

        if ($distances->isEmpty()) {
            return $fairOrdered;
        }

        // Ordena por distância (asc); sem posição vai para o fim (INF).
        // sortBy é estável (PHP 8 asort), então desempata pela fila justa.
        return $fairOrdered
            ->sortBy(static fn (Shift $shift): float => $distances->get((int) $shift->vehicle_id, INF))
            ->values();
    }
}
