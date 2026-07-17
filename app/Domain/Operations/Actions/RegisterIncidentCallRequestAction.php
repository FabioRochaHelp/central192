<?php

declare(strict_types=1);

namespace App\Domain\Operations\Actions;

use App\Domain\Operations\DTOs\RegisterIncidentCallRequestDTO;
use App\Domain\Operations\Events\IncidentCallRequestRegistered;
use App\Domain\Operations\Services\IncidentTimelineRecorder;
use App\Models\Incident;
use App\Models\IncidentCallRequest;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Soma uma nova ligação a uma ocorrência já registrada no mesmo ponto, em vez de abrir outra.
 *
 * O texto informado pelo operador vai para a descrição da ocorrência via
 * {@see AppendIncidentDescriptionAction}, mantendo o histórico anterior e o carimbo
 * de data/operador sob a mesma regra de formatação do restante do sistema.
 */
final class RegisterIncidentCallRequestAction
{
    public function __construct(
        private readonly IncidentTimelineRecorder $timeline,
        private readonly AppendIncidentDescriptionAction $appendDescription,
    ) {}

    public function execute(Incident $incident, RegisterIncidentCallRequestDTO $dto, User $actor): IncidentCallRequest
    {
        $request = DB::transaction(function () use ($incident, $dto, $actor): IncidentCallRequest {
            $request = IncidentCallRequest::create([
                'incident_id' => $incident->id,
                'caller_name' => $dto->callerName,
                'caller_phone' => $dto->callerPhone,
                'notes' => $dto->notes,
                'latitude' => $dto->latitude,
                'longitude' => $dto->longitude,
                'distance_meters' => $dto->distanceMeters,
                'pabx_uniqueid' => $dto->pabxUniqueid,
                'created_by' => $actor->id,
            ]);

            $this->timeline->record(
                $incident,
                'incident_call_request_registered',
                [
                    'call_request_id' => $request->id,
                    'caller_name' => $dto->callerName,
                    'caller_phone' => $dto->callerPhone,
                    'distance_meters' => $dto->distanceMeters,
                ],
                $actor,
            );

            if ($dto->notes !== null && trim($dto->notes) !== '') {
                $this->appendDescription->execute($incident, $dto->notes, $actor);
            }

            return $request;
        });

        IncidentCallRequestRegistered::dispatch(
            $incident->fresh(),
            1 + $incident->callRequests()->count(),
        );

        return $request;
    }
}
