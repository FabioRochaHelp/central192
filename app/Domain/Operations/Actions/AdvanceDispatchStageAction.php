<?php

declare(strict_types=1);

namespace App\Domain\Operations\Actions;

use App\Domain\Operations\DTOs\AdvanceDispatchStageDTO;
use App\Domain\Operations\Enums\DispatchStage;
use App\Domain\Operations\Enums\IncidentStatus;
use App\Domain\Operations\Events\DispatchStageAdvanced;
use App\Domain\Operations\Services\IncidentTimelineRecorder;
use App\Models\Incident;
use App\Models\IncidentDispatch;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final class AdvanceDispatchStageAction
{
    public function __construct(
        private IncidentTimelineRecorder $timeline,
    ) {}

    public function execute(AdvanceDispatchStageDTO $dto): IncidentDispatch
    {
        return DB::transaction(function () use ($dto): IncidentDispatch {
            /** @var IncidentDispatch $dispatch */
            $dispatch = IncidentDispatch::query()
                ->whereNull('deleted_at')
                ->findOrFail($dto->incidentDispatchId);

            $current = $dispatch->stage;
            $target = $dto->targetStage;

            if ($current === $target) {
                return $dispatch;
            }

            $ci = $current->index();
            $ti = $target->index();

            if ($ti !== $ci + 1) {
                throw new RuntimeException('Transição de etapa inválida: progressão sequencial obrigatória.');
            }

            $now = now();
            $dispatch->stage = $target;
            $dispatch->applyStageTimestamp($target, $now);
            $dispatch->save();

            /** @var Incident $incident */
            $incident = $dispatch->incident()->firstOrFail();

            if ($target->index() >= DispatchStage::DepartedBase->index()
                && in_array($incident->status, [IncidentStatus::Dispatched, IncidentStatus::Open], true)) {
                $incident->update(['status' => IncidentStatus::InProgress]);
            }

            $this->timeline->record($incident, 'dispatch_stage_advanced', [
                'stage' => $target->value,
                'operator_user_id' => $dto->operatorUserId,
                'shift_id' => $dispatch->shift_id,
                'incident_dispatch_id' => $dispatch->id,
            ]);

            DispatchStageAdvanced::dispatch($incident->fresh(), $dispatch->fresh());

            return $dispatch->fresh();
        });
    }
}
