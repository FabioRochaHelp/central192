<?php

declare(strict_types=1);

namespace App\Domain\Operations\Actions;

use App\Domain\Operations\DTOs\FinalizeIncidentClosureDTO;
use App\Domain\Operations\Enums\IncidentStatus;
use App\Domain\Operations\Events\IncidentFinalized;
use App\Domain\Operations\Services\IncidentTimelineRecorder;
use App\Models\Incident;
use App\Models\IncidentDispatch;
use App\Support\Operations\IncidentOperationalState;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final class FinalizeIncidentClosureAction
{
    public function __construct(
        private IncidentTimelineRecorder $timeline,
    ) {}

    public function execute(FinalizeIncidentClosureDTO $dto): void
    {
        DB::transaction(function () use ($dto): void {
            /** @var Incident $incident */
            $incident = Incident::query()->with('nature')->findOrFail($dto->incidentId);

            if (IncidentOperationalState::activeDispatchCount($incident) > 0) {
                throw new RuntimeException('Ainda há viaturas empenhadas nesta ocorrência.');
            }

            if (IncidentOperationalState::shouldMarkIncidentQta($incident)) {
                throw new RuntimeException('Ocorrência encerrada como QTA; não é necessário escolher viatura principal.');
            }

            $participated = IncidentOperationalState::closureCandidateDispatches($incident);

            if ($participated->isEmpty()) {
                throw new RuntimeException('Nenhuma viatura participou desta ocorrência.');
            }

            $validShift = $participated->contains(
                fn (IncidentDispatch $dispatch): bool => (int) $dispatch->shift_id === $dto->primaryShiftId,
            );

            if (! $validShift) {
                throw new RuntimeException('Viatura principal inválida para esta ocorrência.');
            }

            IncidentDispatch::query()
                ->withTrashed()
                ->where('incident_id', $incident->id)
                ->update(['is_primary' => false]);

            IncidentDispatch::query()
                ->withTrashed()
                ->where('incident_id', $incident->id)
                ->where('shift_id', $dto->primaryShiftId)
                ->update(['is_primary' => true]);

            $modality = $incident->nature?->report_modality;
            $pendingStatus = $modality?->usesFinalReport()
                ? IncidentStatus::PendingFinalReport
                : IncidentStatus::PendingNurseReport;

            $now = now();

            $incident->update([
                'status' => $pendingStatus,
                'primary_shift_id' => $dto->primaryShiftId,
                'returned_base_at' => $now,
            ]);

            $this->timeline->record($incident, 'incident_closed', [
                'primary_shift_id' => $dto->primaryShiftId,
                'operator_user_id' => $dto->operatorUserId,
            ]);

            IncidentFinalized::dispatch($incident->fresh());
        });
    }
}
