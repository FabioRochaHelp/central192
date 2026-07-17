<?php

declare(strict_types=1);

namespace App\Domain\Operations\Actions;

use App\Domain\Operations\DTOs\RegulationDecisionDTO;
use App\Domain\Operations\Enums\IncidentStatus;
use App\Domain\Operations\Enums\RegulationResource;
use App\Domain\Operations\Events\RegulationDecided;
use App\Domain\Operations\Services\IncidentTimelineRecorder;
use App\Models\Incident;
use App\Models\IncidentRegulation;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Registra a decisão do médico regulador e roteia o status da ocorrência:
 * envio de recurso → OPEN (fila de despacho); demais condutas → REGULATION_DENIED.
 *
 * @see docs/regulacao/plano-implementacao.md
 */
final class RegisterRegulationDecisionAction
{
    public function __construct(
        private IncidentTimelineRecorder $timeline,
    ) {}

    public function execute(RegulationDecisionDTO $dto): IncidentRegulation
    {
        return DB::transaction(function () use ($dto): IncidentRegulation {
            /** @var Incident $incident */
            $incident = Incident::query()
                ->with('regulation')
                ->lockForUpdate()
                ->findOrFail($dto->incidentId);

            if (! $incident->status->isUnderRegulation()) {
                throw new RuntimeException('Ocorrência não está em regulação.');
            }

            $decidedAt = now();

            $regulation = $incident->regulation ?? new IncidentRegulation([
                'municipio_id' => $incident->municipio_id,
                'incident_id' => $incident->id,
                'assumed_at' => $decidedAt,
            ]);

            $baseline = $incident->call_received_at ?? $incident->occurred_at ?? $regulation->assumed_at;
            $responseTime = $baseline !== null
                ? (int) max(0, $decidedAt->diffInSeconds($baseline, absolute: true))
                : null;

            $regulation->fill([
                'regulator_user_id' => $dto->regulatorUserId,
                'status' => $dto->decision,
                'priority' => $dto->priority,
                'diagnostic_hypothesis' => $dto->diagnosticHypothesis,
                'recommended_resource' => $dto->decision->authorizesDispatch()
                    ? ($dto->recommendedResource ?? RegulationResource::Usb)
                    : null,
                'guidance_notes' => $dto->guidanceNotes,
                'refusal_reason' => $dto->refusalReason,
                'transfer_target' => $dto->transferTarget,
                'decided_at' => $decidedAt,
                'response_time_seconds' => $responseTime,
            ]);
            $regulation->save();

            $nextStatus = $dto->decision->authorizesDispatch()
                ? IncidentStatus::Open
                : IncidentStatus::RegulationDenied;

            $incident->update([
                'status' => $nextStatus,
                'manchester_risk' => $dto->priority ?? $incident->manchester_risk,
            ]);

            $this->timeline->record($incident, 'regulation_decided', [
                'decision' => $dto->decision->value,
                'recommended_resource' => $regulation->recommended_resource?->value,
                'priority' => $dto->priority?->value,
                'regulator_user_id' => $dto->regulatorUserId,
                'response_time_seconds' => $responseTime,
            ]);

            RegulationDecided::dispatch($incident->fresh(), $regulation->fresh());

            return $regulation->fresh();
        });
    }
}
