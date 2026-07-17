<?php

declare(strict_types=1);

namespace App\Domain\Operations\Actions;

use App\Domain\Operations\DTOs\CreateIncidentDTO;
use App\Domain\Operations\Enums\IncidentStatus;
use App\Domain\Operations\Services\TalaoIssuer;
use App\Models\Incident;
use Illuminate\Support\Facades\DB;

/**
 * Registra chamadas simples (Não completada / Trote / Administrativo).
 *
 * Emite talão (restrição NOT NULL no banco), mas não grava timeline
 * nem dispara eventos de despacho — serve apenas para contabilização.
 */
final class CreateSimpleCallLogAction
{
    public function __construct(private TalaoIssuer $talaoIssuer) {}

    public function execute(CreateIncidentDTO $dto): Incident
    {
        return DB::transaction(function () use ($dto): Incident {
            $occurredAt = $dto->occurredAt ?? now();
            $talao = $this->talaoIssuer->next($occurredAt);

            return Incident::create([
                'municipio_id' => $dto->municipioId,
                'dispatch_year' => (int) $occurredAt->format('Y'),
                'talao' => $talao,
                'status' => IncidentStatus::Logged,
                'nature_id' => null,
                'occurred_at' => $occurredAt,
                'call_received_at' => $occurredAt,
                'description' => $dto->description ?: null,
                'caller_name' => $dto->callerName,
                'caller_phone' => $dto->callerPhone,
                'patient_call_type' => $dto->callType->value,
                'created_by' => $dto->createdByUserId,
            ]);
        });
    }
}
