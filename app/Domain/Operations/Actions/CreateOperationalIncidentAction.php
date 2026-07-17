<?php

declare(strict_types=1);

namespace App\Domain\Operations\Actions;

use App\Domain\Operations\DTOs\CreateIncidentDTO;
use App\Domain\Operations\Enums\IncidentStatus;
use App\Domain\Operations\Events\IncidentCreated;
use App\Domain\Operations\Services\IncidentTimelineRecorder;
use App\Domain\Operations\Services\TalaoIssuer;
use App\Models\Incident;
use App\Models\Nature;
use Illuminate\Support\Facades\DB;

final class CreateOperationalIncidentAction
{
    public function __construct(
        private TalaoIssuer $talaoIssuer,
        private IncidentTimelineRecorder $timeline,
    ) {}

    public function execute(CreateIncidentDTO $dto): Incident
    {
        return DB::transaction(function () use ($dto): Incident {
            $occurredAt = $dto->occurredAt ?? now();
            $callReceivedAt = $dto->callReceivedAt ?? $occurredAt;

            $talao = $this->talaoIssuer->next($occurredAt);

            $initialStatus = $this->resolveInitialStatus($dto->natureId);

            $incident = Incident::create([
                'municipio_id' => $dto->municipioId,
                'dispatch_year' => (int) $occurredAt->format('Y'),
                'talao' => $talao,
                'status' => $initialStatus,
                'nature_id' => $dto->natureId,
                'occurred_at' => $occurredAt,
                'call_received_at' => $callReceivedAt,
                'address_line' => $dto->addressLine,
                'number' => $dto->number,
                'district' => $dto->district,
                'city' => $dto->city,
                'reference_notes' => $dto->referenceNotes,
                'description' => $dto->description,
                'caller_name' => $dto->callerName,
                'caller_phone' => $dto->callerPhone,
                'pabx_uniqueid' => $dto->pabxUniqueid,
                'patient_age' => $dto->patientAge,
                'patient_sex' => $dto->patientSex,
                'patient_name' => $dto->patientName,
                'patient_call_type' => $dto->callType->value,
                'manchester_risk' => $dto->manchesterRisk?->value,
                'latitude' => $dto->latitude,
                'longitude' => $dto->longitude,
                'expected_victim_total' => $dto->expectedVictimTotal,
                'created_by' => $dto->createdByUserId,
                'is_qta' => $dto->isQta,
                'total_death_count' => $dto->totalDeathCount,
                'protected_area_id' => $dto->protectedAreaId,
            ]);

            $this->timeline->record($incident, 'incident_created', [
                'talao' => $talao,
                'call_type' => $dto->callType->value,
                'manchester_risk' => $dto->manchesterRisk?->value,
            ]);

            if ($initialStatus === IncidentStatus::PendingRegulation) {
                $this->timeline->record($incident, 'regulation_queued', [
                    'talao' => $talao,
                ]);
            }

            IncidentCreated::dispatch($incident);

            return $incident->fresh();
        });
    }

    /**
     * Naturezas de saúde (SAMU) entram na fila de regulação médica antes do despacho;
     * as demais (Bombeiros/salvamento) seguem direto para a fila de despacho.
     */
    private function resolveInitialStatus(?int $natureId): IncidentStatus
    {
        if ($natureId === null) {
            return IncidentStatus::Open;
        }

        $nature = Nature::query()->find($natureId);

        return $nature !== null && $nature->requiresMedicalRegulation()
            ? IncidentStatus::PendingRegulation
            : IncidentStatus::Open;
    }
}
