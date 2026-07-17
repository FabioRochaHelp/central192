<?php

declare(strict_types=1);

namespace App\Support\Operations;

use App\Domain\Operations\Enums\IncidentStatus;
use App\Domain\Operations\Enums\ShiftStatus;
use App\Models\Incident;
use App\Models\IncidentDispatch;
use App\Models\Shift;
use Illuminate\Support\Collection;

final class IncidentOperationalState
{
    /** @return Collection<int, IncidentDispatch> */
    public static function activeDispatches(Incident $incident): Collection
    {
        return $incident->dispatches()
            ->whereNull('deleted_at')
            ->with(['shift.vehicle'])
            ->orderBy('id')
            ->get();
    }

    public static function activeDispatchCount(Incident $incident): int
    {
        return $incident->dispatches()->whereNull('deleted_at')->count();
    }

    public static function activeDispatchCountForShift(Shift $shift): int
    {
        return $shift->incidentDispatches()->whereNull('deleted_at')->count();
    }

    public static function releaseShiftIfIdle(Shift $shift): void
    {
        if (self::activeDispatchCountForShift($shift) > 0) {
            return;
        }

        $shift->update([
            'status' => ShiftStatus::Disponivel,
            'status_legacy' => 1,
            'available_at' => now(),
        ]);
    }

    public static function markShiftEmpenhado(Shift $shift): void
    {
        if ($shift->status === ShiftStatus::Empenhado) {
            return;
        }

        $shift->update([
            'status' => ShiftStatus::Empenhado,
            'status_legacy' => 2,
        ]);
    }

    public static function shouldMarkIncidentQta(Incident $incident): bool
    {
        if (self::activeDispatchCount($incident) > 0) {
            return false;
        }

        $participations = $incident->dispatches()->withTrashed()->get();

        if ($participations->isEmpty()) {
            return false;
        }

        return $participations->every(
            fn (IncidentDispatch $dispatch): bool => $dispatch->cancelled_at_scene_at !== null,
        );
    }

    public static function applyIncidentQta(Incident $incident): void
    {
        $incident->update([
            'status' => IncidentStatus::Qta,
            'is_qta' => true,
        ]);
    }

    /** @return Collection<int, IncidentDispatch> */
    public static function closureCandidateDispatches(Incident $incident): Collection
    {
        return $incident->dispatches()
            ->withTrashed()
            ->with(['shift.vehicle'])
            ->whereNull('cancelled_at_scene_at')
            ->orderByDesc('id')
            ->get()
            ->unique('shift_id')
            ->values();
    }

    public static function dispatchableStatuses(): array
    {
        return [
            IncidentStatus::Open,
            IncidentStatus::Dispatched,
            IncidentStatus::InProgress,
        ];
    }
}
