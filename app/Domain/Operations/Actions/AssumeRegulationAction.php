<?php

declare(strict_types=1);

namespace App\Domain\Operations\Actions;

use App\Domain\Operations\Enums\IncidentStatus;
use App\Domain\Operations\Events\RegulationAssumed;
use App\Domain\Operations\Services\IncidentTimelineRecorder;
use App\Models\Incident;
use App\Models\IncidentRegulation;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Médico regulador assume uma ocorrência da fila de regulação.
 *
 * @see docs/regulacao/plano-implementacao.md
 */
final class AssumeRegulationAction
{
    public function __construct(
        private IncidentTimelineRecorder $timeline,
    ) {}

    public function execute(int $incidentId, User $regulator): IncidentRegulation
    {
        return DB::transaction(function () use ($incidentId, $regulator): IncidentRegulation {
            /** @var Incident $incident */
            $incident = Incident::query()
                ->with('regulation')
                ->lockForUpdate()
                ->findOrFail($incidentId);

            if (! $incident->status->isUnderRegulation()) {
                throw new RuntimeException('Ocorrência não está na fila de regulação.');
            }

            $regulation = $incident->regulation;

            // Já assumida por outro médico e ainda em andamento: bloqueia.
            if ($regulation !== null
                && $regulation->regulator_user_id !== null
                && $regulation->regulator_user_id !== $regulator->id
                && $incident->status === IncidentStatus::InRegulation) {
                throw new RuntimeException('Ocorrência já está em regulação por outro médico.');
            }

            $regulation ??= new IncidentRegulation([
                'municipio_id' => $incident->municipio_id,
                'incident_id' => $incident->id,
            ]);

            $regulation->regulator_user_id = $regulator->id;
            $regulation->assumed_at = $regulation->assumed_at ?? now();
            $regulation->save();

            if ($incident->status !== IncidentStatus::InRegulation) {
                $incident->update(['status' => IncidentStatus::InRegulation]);
            }

            $this->timeline->record($incident, 'regulation_assumed', [
                'regulator_user_id' => $regulator->id,
            ], $regulator);

            RegulationAssumed::dispatch($incident->fresh(), $regulator->id);

            return $regulation->fresh();
        });
    }
}
