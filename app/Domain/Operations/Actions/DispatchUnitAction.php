<?php

declare(strict_types=1);

namespace App\Domain\Operations\Actions;

use App\Domain\Operations\DTOs\DispatchUnitDTO;
use App\Domain\Operations\Enums\DispatchStage;
use App\Domain\Operations\Enums\IncidentStatus;
use App\Domain\Operations\Events\UnitDispatched;
use App\Domain\Operations\Services\IncidentTimelineRecorder;
use App\Models\Incident;
use App\Models\IncidentDispatch;
use App\Models\Shift;
use App\Support\Operations\IncidentOperationalState;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final class DispatchUnitAction
{
    public function __construct(
        private IncidentTimelineRecorder $timeline,
    ) {}

    public function execute(DispatchUnitDTO $dto): IncidentDispatch
    {
        $lock = Cache::lock('dispatch:vehicle:'.$dto->vehicleId, 15);

        return $lock->block(10, function () use ($dto): IncidentDispatch {
            return DB::transaction(function () use ($dto): IncidentDispatch {
                /** @var Incident $incident */
                $incident = Incident::query()->findOrFail($dto->incidentId);

                if (! in_array($incident->status, IncidentOperationalState::dispatchableStatuses(), true)) {
                    throw new RuntimeException('Esta ocorrência não aceita novos empenhos.');
                }

                $shiftQuery = Shift::query()
                    ->operationalForDispatch()
                    ->where('vehicle_id', $dto->vehicleId);

                if ($incident->municipio_id !== null) {
                    $shiftQuery->where('municipio_id', $incident->municipio_id);
                }

                $shift = $shiftQuery->first();

                if ($shift === null) {
                    throw new RuntimeException('Nenhum turno vigente encontrado para esta viatura.');
                }

                if ($incident->municipio_id !== null
                    && (int) $incident->municipio_id !== (int) $shift->municipio_id) {
                    throw new RuntimeException('Viatura não pertence à base desta ocorrência.');
                }

                $alreadyOnIncident = IncidentDispatch::query()
                    ->where('incident_id', $incident->id)
                    ->where('shift_id', $shift->id)
                    ->whereNull('deleted_at')
                    ->exists();

                if ($alreadyOnIncident) {
                    throw new RuntimeException('Esta viatura já está empenhada nesta ocorrência.');
                }

                $now = now();
                $isFirstDispatch = IncidentOperationalState::activeDispatchCount($incident) === 0;

                $dispatch = IncidentDispatch::create([
                    'municipio_id' => $shift->municipio_id,
                    'incident_id' => $incident->id,
                    'shift_id' => $shift->id,
                    'stage' => DispatchStage::Dispatched,
                    'stage_position' => DispatchStage::Dispatched->index() + 1,
                    'dispatched_at' => $now,
                ]);

                IncidentOperationalState::markShiftEmpenhado($shift);

                $incidentUpdates = [
                    'municipio_id' => $shift->municipio_id,
                ];

                if ($isFirstDispatch) {
                    $incidentUpdates['status'] = IncidentStatus::Dispatched;
                    $incidentUpdates['dispatched_at'] = $now;
                }

                $incident->update($incidentUpdates);

                $this->timeline->record($incident, 'unit_dispatched', [
                    'shift_id' => $shift->id,
                    'vehicle_id' => $dto->vehicleId,
                    'operator_user_id' => $dto->operatorUserId,
                    'note' => $dto->note,
                    'support_dispatch' => ! $isFirstDispatch,
                ]);

                UnitDispatched::dispatch($incident->fresh(), $dispatch->fresh());

                return $dispatch->fresh();
            });
        });
    }
}
