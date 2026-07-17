<?php

declare(strict_types=1);

namespace App\Domain\Operations\Actions;

use App\Domain\Operations\Enums\DispatchSceneCancelReason;
use App\Domain\Operations\Enums\DispatchStage;
use App\Domain\Operations\Events\DispatchStageAdvanced;
use App\Domain\Operations\Services\IncidentTimelineRecorder;
use App\Models\Incident;
use App\Models\IncidentDispatch;
use App\Support\Operations\IncidentOperationalState;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final class CancelDispatchAtSceneAction
{
    public function __construct(
        private IncidentTimelineRecorder $timeline,
    ) {}

    public function execute(IncidentDispatch $dispatch, DispatchSceneCancelReason $reason, int $operatorUserId): IncidentDispatch
    {
        return DB::transaction(function () use ($dispatch, $reason, $operatorUserId): IncidentDispatch {
            if ($dispatch->deleted_at !== null) {
                throw new RuntimeException('Despacho não está ativo.');
            }

            if ($dispatch->stage->index() >= DispatchStage::LeftScene->index()) {
                throw new RuntimeException('Esta ocorrência já saiu do local.');
            }

            $now = now();

            $dispatch->stage = DispatchStage::LeftScene;
            $dispatch->applyStageTimestamp(DispatchStage::LeftScene, $now);
            if ($dispatch->arrived_scene_at === null) {
                $dispatch->arrived_scene_at = $now;
            }
            $dispatch->cancelled_at_scene_at = $now;
            $dispatch->scene_cancel_reason = $reason->value;
            $dispatch->released_at = $now;
            $dispatch->save();
            $dispatch->delete();

            /** @var Incident $incident */
            $incident = $dispatch->incident()->firstOrFail();

            $shift = $dispatch->shift;
            if ($shift !== null) {
                IncidentOperationalState::releaseShiftIfIdle($shift);
            }

            $this->timeline->record($incident, 'dispatch_scene_cancelled', [
                'reason' => $reason->value,
                'reason_label' => $reason->label(),
                'shift_id' => $dispatch->shift_id,
                'operator_user_id' => $operatorUserId,
                'stage' => DispatchStage::LeftScene->value,
                'incident_dispatch_id' => $dispatch->id,
            ]);

            if (IncidentOperationalState::shouldMarkIncidentQta($incident)) {
                IncidentOperationalState::applyIncidentQta($incident);
            }

            DispatchStageAdvanced::dispatch($incident->fresh(), $dispatch->fresh());

            return $dispatch->fresh();
        });
    }
}
