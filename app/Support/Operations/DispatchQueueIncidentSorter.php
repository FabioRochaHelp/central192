<?php

declare(strict_types=1);

namespace App\Support\Operations;

use App\Domain\Operations\Enums\CallType;
use App\Models\Incident;
use Illuminate\Support\Collection;

final class DispatchQueueIncidentSorter
{
    /**
     * Maior prioridade primeiro: U → L → N (demais ao final).
     *
     * @param  Collection<int, Incident>  $incidents
     * @return Collection<int, Incident>
     */
    public static function sort(Collection $incidents): Collection
    {
        return $incidents
            ->sortBy(fn (Incident $incident): int => $incident->occurred_at?->getTimestamp() ?? 0)
            ->sortBy(fn (Incident $incident): int => $incident->manchester_risk?->sortOrder() ?? 99)
            ->sortBy(fn (Incident $incident): int => CallType::tryFrom((string) $incident->patient_call_type)?->dispatchQueueSortOrder() ?? 99)
            ->values();
    }
}
