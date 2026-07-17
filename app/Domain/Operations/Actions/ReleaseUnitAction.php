<?php

declare(strict_types=1);

namespace App\Domain\Operations\Actions;

use App\Domain\Operations\DTOs\ReleaseUnitDTO;
use App\Domain\Operations\DTOs\ReleaseUnitResult;
use App\Domain\Operations\Events\UnitReleased;
use App\Domain\Operations\Services\IncidentTimelineRecorder;
use App\Models\Incident;
use App\Models\IncidentDispatch;
use App\Models\Shift;
use App\Support\Operations\DispatchReleaseRules;
use App\Support\Operations\IncidentOperationalState;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final class ReleaseUnitAction
{
    public function __construct(
        private IncidentTimelineRecorder $timeline,
    ) {}

    public function execute(ReleaseUnitDTO $dto): ReleaseUnitResult
    {
        $lock = Cache::lock('dispatch:vehicle:'.$dto->vehicleId, 15);

        return $lock->block(10, function () use ($dto): ReleaseUnitResult {
            return DB::transaction(function () use ($dto): ReleaseUnitResult {
                /** @var Incident $incident */
                $incident = Incident::query()->with('nature')->findOrFail($dto->incidentId);

                /** @var Shift|null $shift */
                $shift = Shift::query()
                    ->where('vehicle_id', $dto->vehicleId)
                    ->where('municipio_id', $incident->municipio_id)
                    ->where('ends_at', '>=', now())
                    ->first();

                if ($shift === null) {
                    throw new RuntimeException('Turno ativo não encontrado para a viatura.');
                }

                /** @var IncidentDispatch|null $dispatch */
                $dispatch = IncidentDispatch::query()
                    ->where('incident_id', $incident->id)
                    ->where('shift_id', $shift->id)
                    ->whereNull('deleted_at')
                    ->latest('id')
                    ->first();

                if ($dispatch === null) {
                    throw new RuntimeException('Despacho ativo não encontrado.');
                }

                if (! DispatchReleaseRules::canReleaseVehicle($dispatch, $incident)) {
                    $label = DispatchReleaseRules::requiredReleaseStage($incident)->label();
                    throw new RuntimeException("Encerramento só é permitido após etapa \"{$label}\".");
                }

                $now = now();

                $dispatch->update([
                    'returned_base_at' => $now,
                    'released_at' => $now,
                ]);
                $dispatch->delete();

                IncidentOperationalState::releaseShiftIfIdle($shift);

                $this->timeline->record($incident, 'unit_released', [
                    'shift_id' => $shift->id,
                    'vehicle_id' => $dto->vehicleId,
                    'operator_user_id' => $dto->operatorUserId,
                    'incident_dispatch_id' => $dispatch->id,
                ]);

                UnitReleased::dispatch($incident->fresh(), $dto->vehicleId);

                $incident->refresh();

                if (IncidentOperationalState::activeDispatchCount($incident) > 0) {
                    return new ReleaseUnitResult(requiresIncidentClosure: false, incident: $incident);
                }

                if (IncidentOperationalState::shouldMarkIncidentQta($incident)) {
                    IncidentOperationalState::applyIncidentQta($incident);

                    return new ReleaseUnitResult(requiresIncidentClosure: false, incident: $incident->fresh());
                }

                return new ReleaseUnitResult(requiresIncidentClosure: true, incident: $incident);
            });
        });
    }
}
