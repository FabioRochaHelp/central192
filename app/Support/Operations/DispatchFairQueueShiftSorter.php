<?php

declare(strict_types=1);

namespace App\Support\Operations;

use App\Models\Shift;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

final class DispatchFairQueueShiftSorter
{
    /**
     * Fila justa de empenho: viatura há mais tempo na base primeiro.
     *
     * @param  Collection<int, Shift>  $shifts
     * @return Collection<int, Shift>
     */
    public static function sort(Collection $shifts): Collection
    {
        return $shifts
            ->sortBy(fn (Shift $shift): int => $shift->id)
            ->sortBy(fn (Shift $shift): int => self::availableAtTimestamp($shift))
            ->values();
    }

    public static function availableAtTimestamp(Shift $shift): int
    {
        return self::availableAt($shift)->getTimestamp();
    }

    public static function availableAt(Shift $shift): CarbonInterface
    {
        return $shift->available_at
            ?? $shift->starts_at
            ?? $shift->created_at
            ?? now();
    }
}
