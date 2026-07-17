<?php

declare(strict_types=1);

namespace App\Livewire\Operations;

use App\Domain\Operations\Actions\SaveVictimRecordAction;
use App\Domain\Operations\Actions\SyncStandardInjuryMatrixSitesAction;
use App\Domain\Operations\Support\InjuryMatrixDefinition;
use App\Models\Accessory;
use App\Models\CareLocal;
use App\Models\HealthUnit;
use App\Models\Incident;
use App\Models\InjurySite;
use App\Models\Procedure;
use App\Models\User;
use App\Models\Victim;
use App\Models\VictimType;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Formulário de vítima alinhado a vitima + vitima_has_* (docs/migracao/banco-dados.md).
 */
#[Layout('layouts.app')]
#[Title('Registro de vítima')]
final class VictimRecord extends Component
{
    public Incident $incident;

    public ?Victim $victimModel = null;

    public string $name = '';

    /** @var numeric-string|'' */
    public string $sex = '';

    public string $rg = '';

    /** @var numeric-string|'' */
    public string $age = '';

    public string $ssp = '';

    /** @var numeric-string|'' situacao 1 ou 3 */
    public string $situacao = '';

    /** @var numeric-string|'' */
    public string $status = '';

    public string $hospital = '';

    public string $transporte = '';

    public string $unidade_saude = '';

    /** @var numeric-string|'' */
    public string $health_unit_id = '';

    public string $medico_us = '';

    public string $crm_medico_us = '';

    public string $dados_complementares = '';

    /** @var numeric-string|'' */
    public string $victim_type_id = '';

    /** @var numeric-string|'' */
    public string $care_local_id = '';

    /** '' | '1' | '0' */
    public string $fall_height = '';

    public string $fall_height_meters = '';

    public string $halito_etilico = '';

    public string $burn = '';

    public string $burn_percentage = '';

    public string $vehicle_role = '';

    public string $accident_type = '';

    public string $pupil_notes = '';

    public string $pupil_light_reaction = '';

    public string $pupil_symmetry = '';

    public string $pupil_size = '';

    public string $pupil_side = '';

    public string $witness_name = '';

    public string $witness_rg = '';

    public string $witness_ssp = '';

    public string $death_where = '';

    public string $death_notes = '';

    /** @var list<int|string> */
    public array $procedure_ids = [];

    /** @var list<int|string> */
    public array $accessory_ids = [];

    /** @var list<int|string> */
    public array $injury_site_ids = [];

    public string $selected_injury_region = 'Tórax';

    /** @var list<array<string, mixed>> */
    public array $vital_rows = [];

    public function mount(Incident $incident, ?Victim $victim = null): void
    {
        Gate::authorize('view', $incident);

        $this->incident = $incident;

        app(SyncStandardInjuryMatrixSitesAction::class)->execute();

        if ($victim !== null) {
            abort_unless((int) $victim->incident_id === (int) $incident->id, 404);
            Gate::authorize('update', $victim);
            $this->victimModel = $victim->load(['procedures', 'accessories', 'injurySites', 'vitalSigns']);
            $this->hydrateFromVictim($this->victimModel);
        } else {
            Gate::authorize('recordVictim', $incident);
            $this->vital_rows = [$this->emptyVitalRow()];
        }
    }

    /** @return array<string, mixed> */
    private function emptyVitalRow(): array
    {
        return [
            'recorded_at' => now()->format('Y-m-d\TH:i'),
            'blood_pressure_systolic' => '',
            'blood_pressure_diastolic' => '',
            'heart_rate' => '',
            'respiratory_rate' => '',
            'spo2' => '',
            'temperature' => '',
            'blood_glucose' => '',
            'glasgow_eye' => '',
            'glasgow_verbal' => '',
            'glasgow_motor' => '',
            'glasgow_total' => '',
            'neurological_notes' => '',
            'dominant_side' => '',
        ];
    }

    private function hydrateFromVictim(Victim $v): void
    {
        $this->name = (string) ($v->name ?? '');
        $this->sex = $v->sex !== null ? (string) $v->sex : '';
        $this->rg = (string) ($v->rg ?? '');
        $this->age = $v->age !== null ? (string) $v->age : '';
        $this->ssp = (string) ($v->ssp ?? '');
        $this->situacao = $v->situacao !== null ? (string) $v->situacao : '';
        $this->status = $v->status !== null ? (string) $v->status : '';
        $this->hospital = (string) ($v->hospital ?? '');
        $this->transporte = (string) ($v->transporte ?? '');
        $this->unidade_saude = (string) ($v->unidade_saude ?? '');
        $this->health_unit_id = $v->health_unit_id !== null ? (string) $v->health_unit_id : '';
        $this->medico_us = (string) ($v->medico_us ?? '');
        $this->crm_medico_us = (string) ($v->crm_medico_us ?? '');
        $this->dados_complementares = (string) ($v->dados_complementares ?? '');
        $this->victim_type_id = $v->victim_type_id !== null ? (string) $v->victim_type_id : '';
        $this->care_local_id = $v->care_local_id !== null ? (string) $v->care_local_id : '';
        $this->fall_height = $this->boolToTriState($v->fall_height);
        $this->fall_height_meters = $v->fall_height_meters !== null ? (string) $v->fall_height_meters : '';
        $this->halito_etilico = $this->boolToTriState($v->halito_etilico);
        $this->burn = $this->boolToTriState($v->burn);
        $this->burn_percentage = $v->burn_percentage !== null ? (string) $v->burn_percentage : '';
        $this->vehicle_role = (string) ($v->vehicle_role ?? '');
        $this->accident_type = (string) ($v->accident_type ?? '');
        $this->pupil_notes = (string) ($v->pupil_notes ?? '');
        $this->pupil_light_reaction = (string) ($v->pupil_light_reaction ?? '');
        $this->pupil_symmetry = (string) ($v->pupil_symmetry ?? '');
        $this->pupil_size = (string) ($v->pupil_size ?? '');
        $this->pupil_side = (string) ($v->pupil_side ?? '');
        $this->witness_name = (string) ($v->witness_name ?? '');
        $this->witness_rg = (string) ($v->witness_rg ?? '');
        $this->witness_ssp = (string) ($v->witness_ssp ?? '');
        $this->death_where = (string) ($v->death_where ?? '');
        $this->death_notes = (string) ($v->death_notes ?? '');

        $this->procedure_ids = $v->procedures->modelKeys();
        $this->accessory_ids = $v->accessories->modelKeys();
        $this->injury_site_ids = $v->injurySites->modelKeys();

        $this->vital_rows = $v->vitalSigns->map(function ($vs): array {
            return [
                'recorded_at' => $vs->recorded_at->format('Y-m-d\TH:i'),
                'blood_pressure_systolic' => $vs->blood_pressure_systolic !== null ? (string) $vs->blood_pressure_systolic : '',
                'blood_pressure_diastolic' => $vs->blood_pressure_diastolic !== null ? (string) $vs->blood_pressure_diastolic : '',
                'heart_rate' => $vs->heart_rate !== null ? (string) $vs->heart_rate : '',
                'respiratory_rate' => $vs->respiratory_rate !== null ? (string) $vs->respiratory_rate : '',
                'spo2' => $vs->spo2 !== null ? (string) $vs->spo2 : '',
                'temperature' => $vs->temperature !== null ? (string) $vs->temperature : '',
                'blood_glucose' => $vs->blood_glucose !== null ? (string) $vs->blood_glucose : '',
                'glasgow_eye' => $vs->glasgow_eye !== null ? (string) $vs->glasgow_eye : '',
                'glasgow_verbal' => $vs->glasgow_verbal !== null ? (string) $vs->glasgow_verbal : '',
                'glasgow_motor' => $vs->glasgow_motor !== null ? (string) $vs->glasgow_motor : '',
                'glasgow_total' => $vs->glasgow_total !== null ? (string) $vs->glasgow_total : '',
                'neurological_notes' => (string) ($vs->neurological_notes ?? ''),
                'dominant_side' => (string) ($vs->dominant_side ?? ''),
            ];
        })->values()->all();

        if ($this->vital_rows === []) {
            $this->vital_rows = [$this->emptyVitalRow()];
        }
    }

    private function boolToTriState(?bool $v): string
    {
        if ($v === null) {
            return '';
        }

        return $v ? '1' : '0';
    }

    public function addVitalRow(): void
    {
        $this->vital_rows[] = $this->emptyVitalRow();
    }

    public function removeVitalRow(int $index): void
    {
        unset($this->vital_rows[$index]);
        $this->vital_rows = array_values($this->vital_rows);
        if ($this->vital_rows === []) {
            $this->vital_rows = [$this->emptyVitalRow()];
        }
    }

    public function selectInjuryRegion(string $region): void
    {
        if (! in_array($region, InjuryMatrixDefinition::BODY_REGIONS, true)) {
            return;
        }

        $this->selected_injury_region = $region;
    }

    public function toggleInjurySite(int $siteId): void
    {
        if (! InjurySite::query()->whereKey($siteId)->exists()) {
            return;
        }

        $current = array_map('intval', $this->injury_site_ids);

        if (in_array($siteId, $current, true)) {
            $this->injury_site_ids = array_values(array_filter(
                $current,
                fn (int $id): bool => $id !== $siteId,
            ));

            return;
        }

        $current[] = $siteId;
        $this->injury_site_ids = array_values(array_unique($current));
    }

    public function save(SaveVictimRecordAction $action): void
    {
        $user = Auth::user();
        abort_unless($user instanceof User, 403);

        Gate::authorize('view', $this->incident);

        if ($this->victimModel !== null) {
            Gate::authorize('update', $this->victimModel);
        } else {
            Gate::authorize('recordVictim', $this->incident);
        }

        $payload = $this->validationPayload();

        $validated = Validator::make($payload, [
            'name' => ['required', 'string', 'max:255'],
            'sex' => ['required', Rule::in(['1', '2', '3'])],
            'rg' => ['nullable', 'string', 'max:64'],
            'age' => ['nullable', 'integer', 'min:0', 'max:130'],
            'ssp' => ['nullable', 'string', 'max:64'],
            'situacao' => ['required', Rule::in(['1', '2', '3'])],
            'status' => ['nullable', 'integer', 'min:0', 'max:255'],
            'hospital' => ['nullable', 'string', 'max:255'],
            'transporte' => ['nullable', 'string', 'max:255'],
            'unidade_saude' => ['nullable', 'string', 'max:255'],
            'health_unit_id' => ['nullable', 'integer', 'exists:health_units,id'],
            'medico_us' => ['nullable', 'string', 'max:255'],
            'crm_medico_us' => ['nullable', 'string', 'max:64'],
            'dados_complementares' => ['nullable', 'string', 'max:10000'],
            'victim_type_id' => ['nullable', 'integer', 'exists:victim_types,id'],
            'care_local_id' => ['nullable', 'integer', 'exists:care_locals,id'],
            'fall_height' => ['nullable', Rule::in(['', '0', '1'])],
            'fall_height_meters' => ['nullable', 'numeric', 'min:0', 'max:999.99'],
            'halito_etilico' => ['nullable', Rule::in(['', '0', '1'])],
            'burn' => ['nullable', Rule::in(['', '0', '1'])],
            'burn_percentage' => ['nullable', 'integer', 'min:0', 'max:100'],
            'vehicle_role' => ['nullable', 'string', 'max:64'],
            'accident_type' => ['nullable', 'string', 'max:128'],
            'pupil_notes' => ['nullable', 'string', 'max:2000'],
            'pupil_light_reaction' => ['nullable', Rule::in(['', 'present', 'absent'])],
            'pupil_symmetry' => ['nullable', Rule::in(['', 'isocoric', 'anisocoric'])],
            'pupil_size' => ['nullable', Rule::in(['', 'miotic', 'mydriatic'])],
            'pupil_side' => ['nullable', Rule::in(['', 'right', 'left'])],
            'witness_name' => ['nullable', 'string', 'max:255'],
            'witness_rg' => ['nullable', 'string', 'max:64'],
            'witness_ssp' => ['nullable', 'string', 'max:64'],
            'death_where' => ['nullable', 'string', 'max:255'],
            'death_notes' => ['nullable', 'string', 'max:5000'],
            'procedure_ids' => ['array'],
            'procedure_ids.*' => ['integer', 'exists:procedures,id'],
            'accessory_ids' => ['array'],
            'accessory_ids.*' => ['integer', 'exists:accessories,id'],
            'injury_site_ids' => ['array'],
            'injury_site_ids.*' => ['integer', 'exists:injury_sites,id'],
            'vital_rows' => ['array'],
            'vital_rows.*.recorded_at' => ['nullable', 'date'],
            'vital_rows.*.blood_pressure_systolic' => ['nullable', 'integer', 'min:0', 'max:400'],
            'vital_rows.*.blood_pressure_diastolic' => ['nullable', 'integer', 'min:0', 'max:400'],
            'vital_rows.*.heart_rate' => ['nullable', 'integer', 'min:0', 'max:400'],
            'vital_rows.*.respiratory_rate' => ['nullable', 'integer', 'min:0', 'max:200'],
            'vital_rows.*.spo2' => ['nullable', 'integer', 'min:0', 'max:100'],
            'vital_rows.*.temperature' => ['nullable', 'numeric', 'between:30,45'],
            'vital_rows.*.blood_glucose' => ['nullable', 'integer', 'min:0', 'max:999'],
            'vital_rows.*.glasgow_eye' => ['nullable', 'integer', 'min:1', 'max:4'],
            'vital_rows.*.glasgow_verbal' => ['nullable', 'integer', 'min:1', 'max:5'],
            'vital_rows.*.glasgow_motor' => ['nullable', 'integer', 'min:1', 'max:6'],
            'vital_rows.*.glasgow_total' => ['nullable', 'integer', 'min:3', 'max:15'],
            'vital_rows.*.neurological_notes' => ['nullable', 'string', 'max:2000'],
            'vital_rows.*.dominant_side' => ['nullable', Rule::in(['', 'L', 'R'])],
        ], [], [
            'situacao' => __('Situação'),
        ])->validate();

        $situation = (int) $validated['situacao'];

        $attributes = [
            'name' => $validated['name'],
            'sex' => (int) $validated['sex'],
            'rg' => $validated['rg'] ?: null,
            'age' => isset($validated['age']) ? (int) $validated['age'] : null,
            'ssp' => $validated['ssp'] ?: null,
            'situacao' => (int) $validated['situacao'],
            'status' => isset($validated['status']) ? (int) $validated['status'] : null,
            'hospital' => $situation === 1 ? ($validated['hospital'] ?: null) : null,
            'transporte' => $situation === 1 ? ($validated['transporte'] ?: null) : null,
            'unidade_saude' => $situation === 1 ? ($validated['unidade_saude'] ?: null) : null,
            'health_unit_id' => $situation === 1 ? ($validated['health_unit_id'] ?? null) : null,
            'medico_us' => $situation === 1 ? ($validated['medico_us'] ?: null) : null,
            'crm_medico_us' => $situation === 1 ? ($validated['crm_medico_us'] ?: null) : null,
            'dados_complementares' => $validated['dados_complementares'] ?: null,
            'victim_type_id' => $situation === 1 ? ($validated['victim_type_id'] ?? null) : null,
            'care_local_id' => in_array($situation, [1, 2], true) ? ($validated['care_local_id'] ?? null) : null,
            'fall_height' => $situation === 1 ? $this->triStateToBool($validated['fall_height'] ?? '') : null,
            'fall_height_meters' => $situation === 1 && isset($validated['fall_height_meters']) ? (float) $validated['fall_height_meters'] : null,
            'halito_etilico' => $situation === 1 ? $this->triStateToBool($validated['halito_etilico'] ?? '') : null,
            'burn' => $situation === 1 ? $this->triStateToBool($validated['burn'] ?? '') : null,
            'burn_percentage' => $situation === 1 && isset($validated['burn_percentage']) ? (int) $validated['burn_percentage'] : null,
            'vehicle_role' => $situation === 1 ? ($validated['vehicle_role'] ?: null) : null,
            'accident_type' => $situation === 1 ? ($validated['accident_type'] ?: null) : null,
            'pupil_notes' => $situation === 1 ? ($validated['pupil_notes'] ?: null) : null,
            'pupil_light_reaction' => $situation === 1 ? ($validated['pupil_light_reaction'] ?: null) : null,
            'pupil_symmetry' => $situation === 1 ? ($validated['pupil_symmetry'] ?: null) : null,
            'pupil_size' => $situation === 1 ? ($validated['pupil_size'] ?: null) : null,
            'pupil_side' => $situation === 1 && ($validated['pupil_symmetry'] ?? '') === 'anisocoric' ? ($validated['pupil_side'] ?: null) : null,
            'witness_name' => $situation === 3 ? ($validated['witness_name'] ?: null) : null,
            'witness_rg' => $situation === 3 ? ($validated['witness_rg'] ?: null) : null,
            'witness_ssp' => $situation === 3 ? ($validated['witness_ssp'] ?: null) : null,
            'death_where' => $situation === 2 ? ($validated['death_where'] ?: null) : null,
            'death_notes' => $situation === 2 ? ($validated['death_notes'] ?: null) : null,
        ];

        $vitalFiltered = [];
        if ($situation === 1) {
            foreach ($validated['vital_rows'] ?? [] as $row) {
                if (! empty($row['recorded_at']) && $this->hasVitalClinicalData($row)) {
                    $vitalFiltered[] = $row;
                }
            }
        }

        $procedureIds = $situation === 1 ? array_map('intval', $validated['procedure_ids'] ?? []) : [];
        $accessoryIds = $situation === 1 ? array_map('intval', $validated['accessory_ids'] ?? []) : [];
        $injurySiteIds = $situation === 1 ? array_map('intval', $validated['injury_site_ids'] ?? []) : [];

        try {
            $action->execute(
                $this->incident->fresh(),
                $user,
                $this->victimModel,
                $attributes,
                $procedureIds,
                $accessoryIds,
                $injurySiteIds,
                $vitalFiltered,
            );
        } catch (ValidationException $e) {
            throw $e;
        } catch (\Throwable $e) {
            report($e);
            $this->addError('save', __('Não foi possível salvar o registro da vítima.'));

            return;
        }

        $this->redirect(route('operations.incidents.show', $this->incident), navigate: true);
    }

    /** @return array<string, mixed> */
    private function validationPayload(): array
    {
        $normEmptyInt = fn (?string $v): ?int => ($v === null || $v === '') ? null : (int) $v;

        $vitalNormalized = [];
        foreach ($this->vital_rows as $row) {
            $glasgowEye = $normEmptyInt($row['glasgow_eye'] ?? null);
            $glasgowVerbal = $normEmptyInt($row['glasgow_verbal'] ?? null);
            $glasgowMotor = $normEmptyInt($row['glasgow_motor'] ?? null);
            $glasgowTotal = ($glasgowEye !== null && $glasgowVerbal !== null && $glasgowMotor !== null)
                ? $glasgowEye + $glasgowVerbal + $glasgowMotor
                : $normEmptyInt($row['glasgow_total'] ?? null);

            $vitalNormalized[] = [
                'recorded_at' => ($row['recorded_at'] ?? '') === '' ? null : $row['recorded_at'],
                'blood_pressure_systolic' => $normEmptyInt($row['blood_pressure_systolic'] ?? null),
                'blood_pressure_diastolic' => $normEmptyInt($row['blood_pressure_diastolic'] ?? null),
                'heart_rate' => $normEmptyInt($row['heart_rate'] ?? null),
                'respiratory_rate' => $normEmptyInt($row['respiratory_rate'] ?? null),
                'spo2' => $normEmptyInt($row['spo2'] ?? null),
                'temperature' => ($row['temperature'] ?? '') === '' ? null : $row['temperature'],
                'blood_glucose' => $normEmptyInt($row['blood_glucose'] ?? null),
                'glasgow_eye' => $glasgowEye,
                'glasgow_verbal' => $glasgowVerbal,
                'glasgow_motor' => $glasgowMotor,
                'glasgow_total' => $glasgowTotal,
                'neurological_notes' => $row['neurological_notes'] ?? '',
                'dominant_side' => $row['dominant_side'] ?? '',
            ];
        }

        return [
            'name' => $this->name,
            'sex' => $this->sex === '' ? null : $this->sex,
            'rg' => $this->rg,
            'age' => $normEmptyInt($this->age),
            'ssp' => $this->ssp,
            'situacao' => $this->situacao,
            'status' => $normEmptyInt($this->status),
            'hospital' => $this->hospital,
            'transporte' => $this->transporte,
            'unidade_saude' => $this->unidade_saude,
            'health_unit_id' => $normEmptyInt($this->health_unit_id),
            'medico_us' => $this->medico_us,
            'crm_medico_us' => $this->crm_medico_us,
            'dados_complementares' => $this->dados_complementares,
            'victim_type_id' => $normEmptyInt($this->victim_type_id),
            'care_local_id' => $normEmptyInt($this->care_local_id),
            'fall_height' => $this->fall_height,
            'fall_height_meters' => $this->fall_height_meters === '' ? null : $this->fall_height_meters,
            'halito_etilico' => $this->halito_etilico,
            'burn' => $this->burn,
            'burn_percentage' => $normEmptyInt($this->burn_percentage),
            'vehicle_role' => $this->vehicle_role,
            'accident_type' => $this->accident_type,
            'pupil_notes' => $this->pupil_notes,
            'pupil_light_reaction' => $this->pupil_light_reaction,
            'pupil_symmetry' => $this->pupil_symmetry,
            'pupil_size' => $this->pupil_size,
            'pupil_side' => $this->pupil_side,
            'witness_name' => $this->witness_name,
            'witness_rg' => $this->witness_rg,
            'witness_ssp' => $this->witness_ssp,
            'death_where' => $this->death_where,
            'death_notes' => $this->death_notes,
            'procedure_ids' => array_values(array_unique(array_map('intval', $this->procedure_ids))),
            'accessory_ids' => array_values(array_unique(array_map('intval', $this->accessory_ids))),
            'injury_site_ids' => array_values(array_unique(array_map('intval', $this->injury_site_ids))),
            'vital_rows' => $vitalNormalized,
        ];
    }

    private function triStateToBool(?string $v): ?bool
    {
        return match ($v ?? '') {
            '1' => true,
            '0' => false,
            default => null,
        };
    }

    /** @param array<string, mixed> $row */
    private function hasVitalClinicalData(array $row): bool
    {
        foreach (['blood_pressure_systolic', 'blood_pressure_diastolic', 'heart_rate', 'respiratory_rate', 'spo2', 'temperature', 'blood_glucose', 'glasgow_eye', 'glasgow_verbal', 'glasgow_motor', 'glasgow_total', 'neurological_notes', 'dominant_side'] as $key) {
            if (($row[$key] ?? null) !== null && ($row[$key] ?? '') !== '') {
                return true;
            }
        }

        return false;
    }

    /** @param array<string, mixed> $row */
    public function glasgowTotalForRow(array $row): string
    {
        $eye = $this->nullablePositiveInt($row['glasgow_eye'] ?? null);
        $verbal = $this->nullablePositiveInt($row['glasgow_verbal'] ?? null);
        $motor = $this->nullablePositiveInt($row['glasgow_motor'] ?? null);

        if ($eye === null || $verbal === null || $motor === null) {
            return '—';
        }

        return (string) ($eye + $verbal + $motor);
    }

    private function nullablePositiveInt(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        return max(0, (int) $value);
    }

    /**
     * @return array{region: ?string, lesion: ?string, label: string}
     */
    private function injurySiteMatrixDisplay(InjurySite $site): array
    {
        $region = $site->matrix_region;
        $lesion = $site->matrix_lesion;
        if ($region === null || $lesion === null) {
            $inferred = InjuryMatrixDefinition::inferMatrixFromName((string) $site->name);
            $region = $inferred['matrix_region'];
            $lesion = $inferred['matrix_lesion'];
        }

        $label = ($region !== null && $lesion !== null)
            ? $region.' — '.$lesion
            : (string) $site->name;

        return [
            'region' => $region,
            'lesion' => $lesion,
            'label' => $label,
        ];
    }

    /**
     * @param  Collection<int, InjurySite>  $injurySites
     * @return array{
     *     diagrams: list<array{label: string, areas: list<array<string, mixed>>}>,
     *     selected_region: string,
     *     selected_cells: list<array{type: string, site: ?InjurySite, selected: bool}>,
     *     selected_injuries: list<array{site: InjurySite, region: ?string, lesion: ?string, label: string}>,
     *     sidebar_extra_rows: list<array{site: InjurySite, selected: bool}>,
     *     has_any_registered_matrix_site: bool
     * }
     */
    private function injuryMatrix(Collection $injurySites): array
    {
        $sitesByRegion = [];
        /** @var list<int> */
        $matchedIds = [];

        foreach ($injurySites as $site) {
            $placement = InjuryMatrixDefinition::resolvePlacementForCatalog($site);
            if ($placement !== null) {
                [$region, $lesion] = $placement;
                $sitesByRegion[$region][$lesion] = $site;
                $matchedIds[] = (int) $site->id;
            }
        }

        $matchedIdSet = array_fill_keys($matchedIds, true);

        /** @var array<string, list<InjurySite>> */
        $extrasByRegion = [];
        foreach ($injurySites as $site) {
            if (isset($matchedIdSet[(int) $site->id])) {
                continue;
            }

            $dominant = InjuryMatrixDefinition::dominantRegionInName((string) $site->name);
            if ($dominant !== null) {
                $extrasByRegion[$dominant] ??= [];
                $extrasByRegion[$dominant][] = $site;
            }
        }

        foreach ($extrasByRegion as $region => $sites) {
            usort($sites, fn (InjurySite $a, InjurySite $b): int => strcasecmp($a->name, $b->name));
            $extrasByRegion[$region] = $sites;
        }

        $selectedRegion = in_array($this->selected_injury_region, InjuryMatrixDefinition::BODY_REGIONS, true)
            ? $this->selected_injury_region
            : InjuryMatrixDefinition::BODY_REGIONS[0];

        $selectedIds = array_map('intval', $this->injury_site_ids);
        $selectedCells = [];
        foreach (InjuryMatrixDefinition::LESION_TYPES as $type) {
            $site = $sitesByRegion[$selectedRegion][$type] ?? null;
            $selectedCells[] = [
                'type' => $type,
                'site' => $site,
                'selected' => $site instanceof InjurySite && in_array((int) $site->id, $selectedIds, true),
            ];
        }

        $regionOrder = array_flip(InjuryMatrixDefinition::BODY_REGIONS);
        $lesionOrder = array_flip(InjuryMatrixDefinition::LESION_TYPES);

        $selected_injuries = $injurySites
            ->filter(fn (InjurySite $site): bool => in_array((int) $site->id, $selectedIds, true))
            ->map(function (InjurySite $site) use ($regionOrder, $lesionOrder): array {
                $meta = $this->injurySiteMatrixDisplay($site);

                return [
                    'site' => $site,
                    'region' => $meta['region'],
                    'lesion' => $meta['lesion'],
                    'label' => $meta['label'],
                    '_region_sort' => $meta['region'] !== null ? ($regionOrder[$meta['region']] ?? PHP_INT_MAX) : PHP_INT_MAX,
                    '_lesion_sort' => $meta['lesion'] !== null ? ($lesionOrder[$meta['lesion']] ?? PHP_INT_MAX) : PHP_INT_MAX,
                ];
            })
            ->sort(function (array $a, array $b): int {
                return [$a['_region_sort'], $a['_lesion_sort'], $a['label']]
                    <=> [$b['_region_sort'], $b['_lesion_sort'], $b['label']];
            })
            ->values()
            ->map(fn (array $row): array => [
                'site' => $row['site'],
                'region' => $row['region'],
                'lesion' => $row['lesion'],
                'label' => $row['label'],
            ])
            ->all();

        $sidebarExtraSites = array_values($extrasByRegion[$selectedRegion] ?? []);
        $sidebarExtraRows = array_map(function (InjurySite $site) use ($selectedIds): array {
            return [
                'site' => $site,
                'selected' => in_array((int) $site->id, $selectedIds, true),
            ];
        }, $sidebarExtraSites);

        return [
            'diagrams' => $this->injuryBodyDiagrams($sitesByRegion, $extrasByRegion, $selectedIds, $selectedRegion),
            'selected_region' => $selectedRegion,
            'selected_cells' => $selectedCells,
            'selected_injuries' => $selected_injuries,
            'sidebar_extra_rows' => $sidebarExtraRows,
            'has_any_registered_matrix_site' => $injurySites->isNotEmpty(),
        ];
    }

    /**
     * @param  array<string, array<string, InjurySite>>  $sitesByRegion
     * @param  array<string, list<InjurySite>>  $extrasByRegion
     * @param  list<int>  $selectedIds
     * @return list<array{label: string, areas: list<array<string, mixed>>}>
     */
    private function injuryBodyDiagrams(array $sitesByRegion, array $extrasByRegion, array $selectedIds, string $selectedRegion): array
    {
        $front = [
            ['region' => 'Crânio', 'shape' => 'circle', 'x' => 80, 'y' => 28, 'r' => 18],
            ['region' => 'Face', 'shape' => 'rect', 'x' => 62, 'y' => 48, 'width' => 36, 'height' => 22, 'rx' => 10],
            ['region' => 'Pescoço', 'shape' => 'rect', 'x' => 70, 'y' => 72, 'width' => 20, 'height' => 14, 'rx' => 5],
            ['region' => 'Tórax', 'shape' => 'rect', 'x' => 49, 'y' => 88, 'width' => 62, 'height' => 54, 'rx' => 20],
            ['region' => 'Abdome', 'shape' => 'rect', 'x' => 56, 'y' => 145, 'width' => 48, 'height' => 42, 'rx' => 15],
            ['region' => 'Membro superior direito', 'shape' => 'rect', 'x' => 25, 'y' => 94, 'width' => 22, 'height' => 88, 'rx' => 12],
            ['region' => 'Membro superior esquerdo', 'shape' => 'rect', 'x' => 113, 'y' => 94, 'width' => 22, 'height' => 88, 'rx' => 12],
            ['region' => 'Membro inferior direito', 'shape' => 'rect', 'x' => 54, 'y' => 190, 'width' => 23, 'height' => 62, 'rx' => 12],
            ['region' => 'Membro inferior esquerdo', 'shape' => 'rect', 'x' => 83, 'y' => 190, 'width' => 23, 'height' => 62, 'rx' => 12],
        ];

        $back = [
            ['region' => 'Crânio', 'shape' => 'circle', 'x' => 80, 'y' => 28, 'r' => 18],
            ['region' => 'Pescoço', 'shape' => 'rect', 'x' => 70, 'y' => 72, 'width' => 20, 'height' => 14, 'rx' => 5],
            ['region' => 'Dorso', 'shape' => 'rect', 'x' => 49, 'y' => 88, 'width' => 62, 'height' => 96, 'rx' => 22],
            ['region' => 'Membro superior direito', 'shape' => 'rect', 'x' => 25, 'y' => 94, 'width' => 22, 'height' => 88, 'rx' => 12],
            ['region' => 'Membro superior esquerdo', 'shape' => 'rect', 'x' => 113, 'y' => 94, 'width' => 22, 'height' => 88, 'rx' => 12],
            ['region' => 'Membro inferior direito', 'shape' => 'rect', 'x' => 54, 'y' => 190, 'width' => 23, 'height' => 62, 'rx' => 12],
            ['region' => 'Membro inferior esquerdo', 'shape' => 'rect', 'x' => 83, 'y' => 190, 'width' => 23, 'height' => 62, 'rx' => 12],
        ];

        return [
            [
                'label' => __('Frente'),
                'areas' => $this->hydrateInjuryAreas($front, $sitesByRegion, $extrasByRegion, $selectedIds, $selectedRegion),
            ],
            [
                'label' => __('Dorso'),
                'areas' => $this->hydrateInjuryAreas($back, $sitesByRegion, $extrasByRegion, $selectedIds, $selectedRegion),
            ],
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $areas
     * @param  array<string, array<string, InjurySite>>  $sitesByRegion
     * @param  array<string, list<InjurySite>>  $extrasByRegion
     * @param  list<int>  $selectedIds
     * @return list<array<string, mixed>>
     */
    private function hydrateInjuryAreas(array $areas, array $sitesByRegion, array $extrasByRegion, array $selectedIds, string $selectedRegion): array
    {
        return array_map(function (array $area) use ($sitesByRegion, $extrasByRegion, $selectedIds, $selectedRegion): array {
            $region = $area['region'];
            $matrixSites = $sitesByRegion[$region] ?? [];
            $extraSites = $extrasByRegion[$region] ?? [];
            $siteIds = array_merge(
                array_map(static fn (InjurySite $site): int => (int) $site->id, array_values($matrixSites)),
                array_map(static fn (InjurySite $site): int => (int) $site->id, $extraSites),
            );
            $siteIds = array_values(array_unique($siteIds));

            $area['enabled'] = $siteIds !== [];
            $area['selected'] = $region === $selectedRegion;
            $area['selected_count'] = count(array_intersect($siteIds, $selectedIds));

            return $area;
        }, $areas);
    }

    public function render(): View
    {
        $injurySites = InjurySite::query()->orderBy('name')->get();

        return view('livewire.operations.victim-record', [
            'victimTypes' => VictimType::query()->orderBy('name')->get(),
            'careLocals' => CareLocal::query()->orderBy('name')->get(),
            'healthUnits' => HealthUnit::query()->orderBy('name')->get(),
            'procedures' => Procedure::query()->orderBy('name')->get(),
            'accessories' => Accessory::query()->orderBy('name')->get(),
            'injuryMatrix' => $this->injuryMatrix($injurySites),
        ]);
    }
}
