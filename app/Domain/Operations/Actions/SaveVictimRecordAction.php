<?php

declare(strict_types=1);

namespace App\Domain\Operations\Actions;

use App\Domain\Operations\Services\IncidentTimelineRecorder;
use App\Domain\Operations\Support\InjuryMatrixDefinition;
use App\Models\Incident;
use App\Models\InjurySite;
use App\Models\User;
use App\Models\Victim;
use App\Models\VictimInjuryMatrixEntry;
use App\Models\VictimVitalSign;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** Persiste vítima + vínculos (procedimentos, acessórios, locais de ferimento) e sinais vitais. */
final class SaveVictimRecordAction
{
    public function __construct(
        private IncidentTimelineRecorder $timeline,
    ) {}

    /**
     * @param  array<string, mixed>  $attributes  Campos fillable da vítima (exceto municipio_id / incident_id)
     * @param  list<int>  $procedureIds
     * @param  list<int>  $accessoryIds
     * @param  list<int>  $injurySiteIds
     * @param  list<array<string, mixed>>  $vitalRows
     */
    public function execute(
        Incident $incident,
        User $actor,
        ?Victim $existing,
        array $attributes,
        array $procedureIds,
        array $accessoryIds,
        array $injurySiteIds,
        array $vitalRows,
    ): Victim {
        $municipioId = $incident->municipio_id ?? $actor->municipio_id;
        if ($municipioId === null) {
            throw ValidationException::withMessages([
                'scope' => __('Registro de vítima exige ocorrência ou usuário vinculado a uma base (município).'),
            ]);
        }

        return DB::transaction(function () use ($incident, $actor, $existing, $attributes, $municipioId, $procedureIds, $accessoryIds, $injurySiteIds, $vitalRows): Victim {
            if ($existing !== null) {
                $existing->update(array_merge($attributes, [
                    'municipio_id' => $municipioId,
                ]));
                $victim = $existing->fresh();
            } else {
                $victim = Victim::query()->create(array_merge($attributes, [
                    'municipio_id' => $municipioId,
                    'incident_id' => $incident->id,
                ]));
            }

            $victim->procedures()->sync($procedureIds);
            $victim->accessories()->sync($accessoryIds);
            $victim->injurySites()->sync($injurySiteIds);

            VictimInjuryMatrixEntry::query()->where('victim_id', $victim->id)->delete();
            foreach ($injurySiteIds as $injurySiteId) {
                $site = InjurySite::query()->find($injurySiteId);
                if ($site === null) {
                    continue;
                }

                $region = $site->matrix_region;
                $lesion = $site->matrix_lesion;
                if ($region === null || $lesion === null) {
                    $inferred = InjuryMatrixDefinition::inferMatrixFromName((string) $site->name);
                    $region = $inferred['matrix_region'];
                    $lesion = $inferred['matrix_lesion'];
                }

                if ($region === null || $lesion === null) {
                    continue;
                }

                VictimInjuryMatrixEntry::query()->create([
                    'victim_id' => $victim->id,
                    'matrix_region' => $region,
                    'matrix_lesion' => $lesion,
                    'injury_site_id' => $site->id,
                ]);
            }

            $victim->vitalSigns()->delete();
            foreach ($vitalRows as $row) {
                if (($row['recorded_at'] ?? '') === '') {
                    continue;
                }
                VictimVitalSign::query()->create([
                    'victim_id' => $victim->id,
                    'recorded_at' => CarbonImmutable::parse((string) $row['recorded_at']),
                    'blood_pressure_systolic' => $this->nullableInt($row['blood_pressure_systolic'] ?? null),
                    'blood_pressure_diastolic' => $this->nullableInt($row['blood_pressure_diastolic'] ?? null),
                    'heart_rate' => $this->nullableInt($row['heart_rate'] ?? null),
                    'respiratory_rate' => $this->nullableInt($row['respiratory_rate'] ?? null),
                    'spo2' => $this->nullableInt($row['spo2'] ?? null),
                    'temperature' => $this->nullableDecimal($row['temperature'] ?? null),
                    'blood_glucose' => $this->nullableInt($row['blood_glucose'] ?? null),
                    'glasgow_eye' => $this->nullableInt($row['glasgow_eye'] ?? null),
                    'glasgow_verbal' => $this->nullableInt($row['glasgow_verbal'] ?? null),
                    'glasgow_motor' => $this->nullableInt($row['glasgow_motor'] ?? null),
                    'glasgow_total' => $this->nullableInt($row['glasgow_total'] ?? null),
                    'neurological_notes' => $this->nullableString($row['neurological_notes'] ?? null),
                    'dominant_side' => $this->nullableDominant($row['dominant_side'] ?? null),
                ]);
            }

            $this->timeline->record($incident, 'victim_recorded', [
                'victim_id' => $victim->id,
                'is_update' => $existing !== null,
            ], $actor);

            return $victim->load(['procedures', 'accessories', 'injurySites', 'vitalSigns']);
        });
    }

    private function nullableInt(mixed $v): ?int
    {
        if ($v === null || $v === '') {
            return null;
        }

        return (int) $v;
    }

    private function nullableDecimal(mixed $v): ?float
    {
        if ($v === null || $v === '') {
            return null;
        }

        return (float) $v;
    }

    private function nullableString(mixed $v): ?string
    {
        $s = trim((string) ($v ?? ''));

        return $s === '' ? null : $s;
    }

    private function nullableDominant(mixed $v): ?string
    {
        $s = strtoupper(trim((string) ($v ?? '')));

        return in_array($s, ['L', 'R'], true) ? $s : null;
    }
}
