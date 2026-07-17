<?php

declare(strict_types=1);

namespace App\Support\Operations;

use App\Models\Incident;
use App\Models\IncidentDispatch;
use App\Models\Vehicle;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/** Despacho/viatura e intervalo histórico do percurso Traccar de uma ocorrência. */
final class IncidentRouteContext
{
    private function __construct(
        public readonly Incident $incident,
        public readonly ?IncidentDispatch $dispatch,
        public readonly ?Vehicle $vehicle,
        public readonly ?CarbonInterface $from,
        public readonly ?CarbonInterface $to,
    ) {}

    public static function for(Incident $incident): self
    {
        $dispatch = self::resolveDispatch($incident);
        $vehicle = $dispatch?->shift?->vehicle;
        [$from, $to] = self::resolveInterval($incident, $dispatch);

        return new self($incident, $dispatch, $vehicle, $from, $to);
    }

    /**
     * @param  Collection<int, IncidentDispatch>  $candidates
     */
    public static function pickRouteDispatch(Incident $incident, Collection $candidates): ?IncidentDispatch
    {
        if ($candidates->isEmpty()) {
            return null;
        }

        $ordered = $candidates
            ->sortByDesc(fn (IncidentDispatch $dispatch): int => (int) $dispatch->is_primary)
            ->sortByDesc('id')
            ->values();

        $primary = $ordered->first(fn (IncidentDispatch $dispatch): bool => (bool) $dispatch->is_primary);
        if ($primary !== null) {
            return $primary;
        }

        if ($incident->primary_shift_id !== null) {
            $byShift = $ordered->first(
                fn (IncidentDispatch $dispatch): bool => (int) $dispatch->shift_id === (int) $incident->primary_shift_id,
            );

            if ($byShift !== null) {
                return $byShift;
            }
        }

        $active = $ordered->first(fn (IncidentDispatch $dispatch): bool => $dispatch->deleted_at === null);
        if ($active !== null) {
            return $active;
        }

        return $ordered->first();
    }

    /** Viatura vinculada a alguma unidade rastreada (Traccar device_id ou SSX). */
    public function hasDevice(): bool
    {
        $vehicle = $this->vehicle;

        if ($vehicle === null) {
            return false;
        }

        $deviceId = $vehicle->device_id;
        $ssxCode = $vehicle->ssx_integration_code;

        return ($deviceId !== null && $deviceId !== '')
            || ($ssxCode !== null && $ssxCode !== '');
    }

    public function canFetchRoute(): bool
    {
        return $this->hasDevice() && $this->from !== null && $this->to !== null;
    }

    public function isHistoricalReplay(): bool
    {
        return ! self::routeStillOpen($this->incident);
    }

    private static function resolveDispatch(Incident $incident): ?IncidentDispatch
    {
        return self::pickRouteDispatch(
            $incident,
            $incident->dispatches()
                ->withTrashed()
                ->with(['shift.vehicle'])
                ->orderByDesc('is_primary')
                ->orderByDesc('id')
                ->get(),
        );
    }

    /**
     * @return array{0: ?CarbonInterface, 1: ?CarbonInterface}
     */
    private static function resolveInterval(Incident $incident, ?IncidentDispatch $dispatch): array
    {
        $from = $dispatch?->dispatched_at ?? $incident->dispatched_at;

        if ($from === null) {
            return [null, null];
        }

        $to = $dispatch?->returned_base_at
            ?? $dispatch?->released_at
            ?? $incident->returned_base_at;

        if ($to === null && self::routeStillOpen($incident)) {
            $to = now();
        }

        if ($to !== null && $to->lte($from)) {
            $to = $from->copy()->addMinute();
        }

        return [$from, $to];
    }

    private static function routeStillOpen(Incident $incident): bool
    {
        if (IncidentOperationalState::activeDispatchCount($incident) > 0) {
            return true;
        }

        return in_array($incident->status, IncidentOperationalState::dispatchableStatuses(), true);
    }
}
