<?php

declare(strict_types=1);

namespace App\Livewire\Operations;

use App\Domain\Fire\Enums\FireScarStatus;
use App\Domain\Operations\Actions\SaveFinalReportAction;
use App\Domain\Operations\Enums\IncidentReportModality;
use App\Jobs\RequestFireScarAnalysis;
use App\Models\FireScarAnalysis;
use App\Models\Incident;
use App\Models\OperationalSupport;
use App\Models\User;
use App\Services\WeatherService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use RuntimeException;
use Throwable;

/**
 * Relatório final CB — detecta modalidade da natureza e apresenta o sub-formulário correto.
 *
 * @see docs/migracao/modalidades-relatorios.md — IncidentFinalReport
 */
#[Layout('layouts.app')]
#[Title('Relatório final')]
final class IncidentFinalReport extends Component
{
    public Incident $incident;

    // ── Campos base ─────────────────────────────────────────────────────────
    public int $victims_rescued = 0;

    public int $victims_injured = 0;

    public int $victims_deceased = 0;

    public string $resources_summary = '';

    public string $external_support = '';

    public string $observations = '';

    // ── Campos incêndio florestal ────────────────────────────────────────────
    public string $ff_affected_area_ha = '';

    public string $ff_vegetation_type = '';

    public string $ff_fire_behavior = '';

    public string $ff_probable_cause = '';

    public string $ff_discovery_source = '';

    public string $ff_temperature_celsius = '';

    public string $ff_humidity_percent = '';

    public string $ff_wind_speed_kmh = '';

    public string $ff_wind_direction = '';

    public int $ff_personnel_count = 0;

    public bool $ff_aircraft_used = false;

    public string $ff_aircraft_description = '';

    /** @var array<int> IDs de OperationalSupport selecionados */
    public array $ff_external_agencies = [];

    public string $ff_actions_taken = '';

    public bool $ff_fauna_damage = false;

    public string $ff_fauna_damage_description = '';

    public int $ff_structures_affected = 0;

    public int $ff_people_evacuated = 0;

    public string $ff_final_status = '';

    /** null = não buscado | 'ok' | 'no_location' | 'error' */
    public ?string $weatherFetchStatus = null;

    /** Mensagem de feedback ao solicitar análise de cicatriz. */
    public string $fireScarMessage = '';

    // ── Campos incêndio edificação ───────────────────────────────────────────
    public string $fb_building_type = '';

    public string $fb_construction_type = '';

    public string $fb_floors_total = '';

    public string $fb_floors_affected = '';

    public string $fb_affected_area_m2 = '';

    public string $fb_probable_cause = '';

    public string $fb_fire_origin_location = '';

    public bool $fb_hazmat_present = false;

    public string $fb_hazmat_description = '';

    public string $fb_occupants_at_incident = '';

    public int $fb_animals_rescued = 0;

    public int $fb_animals_deceased = 0;

    public int $fb_residents_displaced = 0;

    public string $fb_damage_level = '';

    public bool $fb_vehicle_involved = false;

    public string $fb_external_agencies = '';

    public string $fb_actions_taken = '';

    public string $fb_final_status = '';

    public string $fb_business_name = '';

    public string $fb_business_activity = '';

    // ── Campos salvamento animal ─────────────────────────────────────────────
    public string $ra_animal_category = '';

    public string $ra_animal_species = '';

    public string $ra_animal_breed = '';

    public string $ra_animal_size = '';

    public string $ra_entrapment_type = '';

    public string $ra_entrapment_height_m = '';

    public string $ra_animal_condition_arrival = '';

    public string $ra_equipment_used = '';

    public string $ra_outcome = '';

    public string $ra_owner_name = '';

    public string $ra_owner_phone = '';

    public string $ra_destination_notes = '';

    // ── Campos insetos agressivos ────────────────────────────────────────────
    public string $ri_insect_type = '';

    public string $ri_insect_species = '';

    public string $ri_colony_size_estimate = '';

    public string $ri_nest_location_type = '';

    public string $ri_nest_location_detail = '';

    public string $ri_technique_used = '';

    public string $ri_colony_destination = '';

    public int $ri_people_stung = 0;

    public string $ri_sting_severity = '';

    public bool $ri_prehospital_care = false;

    public string $ri_prehospital_description = '';

    public string $ri_equipment_used = '';

    // ── Campos outros salvamentos ────────────────────────────────────────────
    public string $ro_rescue_subtype = '';

    public int $ro_victim_count = 1;

    public string $ro_situation_description = '';

    public string $ro_victim_condition = '';

    public string $ro_entrapment_description = '';

    public string $ro_rescue_technique = '';

    public string $ro_equipment_used = '';

    public bool $ro_hospital_transport = false;

    public string $ro_hospital_name = '';

    public string $ro_outcome = '';

    public string $ro_duration_minutes = '';

    public function mount(Incident $incident): void
    {
        $incident->loadMissing('nature');
        Gate::authorize('fillFinalReport', $incident);

        $this->incident = $incident->load([
            'nature',
            'finalReport.fireForestReport',
            'finalReport.fireBuildingReport',
            'finalReport.rescueAnimalReport',
            'finalReport.rescueInsectReport',
            'finalReport.rescueOtherReport',
        ]);

        $this->fillFromExisting();
    }

    public function modality(): ?IncidentReportModality
    {
        return $this->incident->nature?->report_modality;
    }

    public function save(SaveFinalReportAction $action): void
    {
        $this->incident->loadMissing('nature');
        Gate::authorize('fillFinalReport', $this->incident);

        $modality = $this->modality();
        if ($modality === null) {
            $this->addError('save', __('Natureza da ocorrência sem modalidade definida.'));

            return;
        }

        $baseValidated = $this->validate($this->baseRules(), [], $this->baseAttributes());
        $specificValidated = $this->validateSpecific($modality);
        $specificMapped = $this->mapSpecificData($modality, $specificValidated);

        $user = Auth::user();
        abort_unless($user instanceof User, 403);

        try {
            $action->execute(
                $this->incident->fresh(),
                $user,
                $modality,
                $this->mapBaseData($baseValidated),
                $specificMapped,
            );
        } catch (AuthorizationException $e) {
            $this->addError('save', $e->getMessage());

            return;
        } catch (RuntimeException $e) {
            $this->addError('save', $e->getMessage());

            return;
        } catch (Throwable $e) {
            report($e);
            $this->addError('save', __('Não foi possível salvar o relatório.'));

            return;
        }

        $this->redirect(route('operations.incidents.show', $this->incident), navigate: true);
    }

    public function loadWeather(WeatherService $weather): void
    {
        if ($this->modality() !== IncidentReportModality::FireForest) {
            return;
        }

        $data = $weather->fetchForIncident($this->incident);

        if ($data === null) {
            $coords = $this->incident->latitude !== null || $this->incident->city !== null;
            $this->weatherFetchStatus = $coords ? 'error' : 'no_location';

            return;
        }

        if ($data['temperature'] !== null) {
            $this->ff_temperature_celsius = (string) $data['temperature'];
        }
        if ($data['humidity'] !== null) {
            $this->ff_humidity_percent = (string) $data['humidity'];
        }
        if ($data['wind_speed'] !== null) {
            $this->ff_wind_speed_kmh = (string) $data['wind_speed'];
        }
        if ($data['wind_direction'] !== null) {
            $this->ff_wind_direction = $data['wind_direction'];
        }

        $this->weatherFetchStatus = 'ok';
    }

    /** Solicita ao serviço externo a análise de cicatriz da área da ocorrência. */
    public function requestFireScar(): void
    {
        if ($this->modality() !== IncidentReportModality::FireForest) {
            return;
        }

        if ($this->incident->latitude === null || $this->incident->longitude === null) {
            $this->addError('fireScar', __('A ocorrência não possui coordenadas para análise de cicatriz.'));

            return;
        }

        $analysis = FireScarAnalysis::create([
            'incident_id' => $this->incident->id,
            'latitude' => $this->incident->latitude,
            'longitude' => $this->incident->longitude,
            'municipio' => $this->incident->city,
            'estado' => $this->incident->municipio?->state,
            'bioma' => null,
            'post_fire_date' => $this->incident->occurred_at?->toDateString(),
            'sensor' => 'Sentinel-2',
            'status' => FireScarStatus::Pendente,
            'requested_at' => now(),
            'created_by' => Auth::id(),
        ]);

        RequestFireScarAnalysis::dispatch($analysis->id);

        $this->fireScarMessage = __('Análise de cicatriz solicitada. Acompanhe em Relatórios › Cicatrizes de incêndio.');
    }

    public function render(): View
    {
        $availableSupports = OperationalSupport::query()
            ->orderBy('name')
            ->get();

        return view('livewire.operations.incident-final-report', [
            'availableSupports' => $availableSupports,
            'fireScars' => $this->modality() === IncidentReportModality::FireForest
                ? FireScarAnalysis::query()
                    ->where('incident_id', $this->incident->id)
                    ->latest('id')
                    ->get()
                : collect(),
        ]);
    }

    // ── Internos ─────────────────────────────────────────────────────────────

    private function fillFromExisting(): void
    {
        $report = $this->incident->finalReport;
        if ($report === null) {
            return;
        }

        $this->victims_rescued = (int) $report->victims_rescued;
        $this->victims_injured = (int) $report->victims_injured;
        $this->victims_deceased = (int) $report->victims_deceased;
        $this->resources_summary = (string) ($report->resources_summary ?? '');
        $this->external_support = (string) ($report->external_support ?? '');
        $this->observations = (string) ($report->observations ?? '');

        if ($forest = $report->fireForestReport) {
            $this->ff_affected_area_ha = (string) ($forest->affected_area_ha ?? '');
            $this->ff_vegetation_type = (string) ($forest->vegetation_type ?? '');
            $this->ff_fire_behavior = (string) ($forest->fire_behavior ?? '');
            $this->ff_probable_cause = (string) ($forest->probable_cause ?? '');
            $this->ff_discovery_source = (string) ($forest->discovery_source ?? '');
            $this->ff_temperature_celsius = (string) ($forest->temperature_celsius ?? '');
            $this->ff_humidity_percent = (string) ($forest->humidity_percent ?? '');
            $this->ff_wind_speed_kmh = (string) ($forest->wind_speed_kmh ?? '');
            $this->ff_wind_direction = (string) ($forest->wind_direction ?? '');
            $this->ff_personnel_count = (int) ($forest->personnel_count ?? 0);
            $this->ff_aircraft_used = (bool) $forest->aircraft_used;
            $this->ff_aircraft_description = (string) ($forest->aircraft_description ?? '');
            $this->ff_external_agencies = array_map('intval', $forest->external_agencies ?? []);
            $this->ff_actions_taken = (string) ($forest->actions_taken ?? '');
            $this->ff_fauna_damage = (bool) $forest->fauna_damage;
            $this->ff_fauna_damage_description = (string) ($forest->fauna_damage_description ?? '');
            $this->ff_structures_affected = (int) ($forest->structures_affected ?? 0);
            $this->ff_people_evacuated = (int) ($forest->people_evacuated ?? 0);
            $this->ff_final_status = (string) ($forest->final_status ?? '');
        }

        if ($building = $report->fireBuildingReport) {
            $this->fb_building_type = (string) ($building->building_type ?? '');
            $this->fb_construction_type = (string) ($building->construction_type ?? '');
            $this->fb_floors_total = (string) ($building->floors_total ?? '');
            $this->fb_floors_affected = (string) ($building->floors_affected ?? '');
            $this->fb_affected_area_m2 = (string) ($building->affected_area_m2 ?? '');
            $this->fb_probable_cause = (string) ($building->probable_cause ?? '');
            $this->fb_fire_origin_location = (string) ($building->fire_origin_location ?? '');
            $this->fb_hazmat_present = (bool) $building->hazmat_present;
            $this->fb_hazmat_description = (string) ($building->hazmat_description ?? '');
            $this->fb_occupants_at_incident = (string) ($building->occupants_at_incident ?? '');
            $this->fb_animals_rescued = (int) ($building->animals_rescued ?? 0);
            $this->fb_animals_deceased = (int) ($building->animals_deceased ?? 0);
            $this->fb_residents_displaced = (int) ($building->residents_displaced ?? 0);
            $this->fb_damage_level = (string) ($building->damage_level ?? '');
            $this->fb_vehicle_involved = (bool) $building->vehicle_involved;
            $this->fb_external_agencies = (string) ($building->external_agencies ?? '');
            $this->fb_actions_taken = (string) ($building->actions_taken ?? '');
            $this->fb_final_status = (string) ($building->final_status ?? '');
            $this->fb_business_name = (string) ($building->business_name ?? '');
            $this->fb_business_activity = (string) ($building->business_activity ?? '');
        }

        if ($animal = $report->rescueAnimalReport) {
            $this->ra_animal_category = (string) ($animal->animal_category ?? '');
            $this->ra_animal_species = (string) ($animal->animal_species ?? '');
            $this->ra_animal_breed = (string) ($animal->animal_breed ?? '');
            $this->ra_animal_size = (string) ($animal->animal_size ?? '');
            $this->ra_entrapment_type = (string) ($animal->entrapment_type ?? '');
            $this->ra_entrapment_height_m = (string) ($animal->entrapment_height_m ?? '');
            $this->ra_animal_condition_arrival = (string) ($animal->animal_condition_arrival ?? '');
            $this->ra_equipment_used = (string) ($animal->equipment_used ?? '');
            $this->ra_outcome = (string) ($animal->outcome ?? '');
            $this->ra_owner_name = (string) ($animal->owner_name ?? '');
            $this->ra_owner_phone = (string) ($animal->owner_phone ?? '');
            $this->ra_destination_notes = (string) ($animal->destination_notes ?? '');
        }

        if ($insect = $report->rescueInsectReport) {
            $this->ri_insect_type = (string) ($insect->insect_type ?? '');
            $this->ri_insect_species = (string) ($insect->insect_species ?? '');
            $this->ri_colony_size_estimate = (string) ($insect->colony_size_estimate ?? '');
            $this->ri_nest_location_type = (string) ($insect->nest_location_type ?? '');
            $this->ri_nest_location_detail = (string) ($insect->nest_location_detail ?? '');
            $this->ri_technique_used = (string) ($insect->technique_used ?? '');
            $this->ri_colony_destination = (string) ($insect->colony_destination ?? '');
            $this->ri_people_stung = (int) ($insect->people_stung ?? 0);
            $this->ri_sting_severity = (string) ($insect->sting_severity ?? '');
            $this->ri_prehospital_care = (bool) $insect->prehospital_care;
            $this->ri_prehospital_description = (string) ($insect->prehospital_description ?? '');
            $this->ri_equipment_used = (string) ($insect->equipment_used ?? '');
        }

        if ($other = $report->rescueOtherReport) {
            $this->ro_rescue_subtype = (string) ($other->rescue_subtype ?? '');
            $this->ro_victim_count = (int) ($other->victim_count ?? 1);
            $this->ro_situation_description = (string) ($other->situation_description ?? '');
            $this->ro_victim_condition = (string) ($other->victim_condition ?? '');
            $this->ro_entrapment_description = (string) ($other->entrapment_description ?? '');
            $this->ro_rescue_technique = (string) ($other->rescue_technique ?? '');
            $this->ro_equipment_used = (string) ($other->equipment_used ?? '');
            $this->ro_hospital_transport = (bool) $other->hospital_transport;
            $this->ro_hospital_name = (string) ($other->hospital_name ?? '');
            $this->ro_outcome = (string) ($other->outcome ?? '');
            $this->ro_duration_minutes = (string) ($other->duration_minutes ?? '');
        }
    }

    /** @return array<string, mixed> */
    private function baseRules(): array
    {
        return [
            'victims_rescued' => ['required', 'integer', 'min:0'],
            'victims_injured' => ['required', 'integer', 'min:0'],
            'victims_deceased' => ['required', 'integer', 'min:0'],
            'resources_summary' => ['nullable', 'string', 'max:5000'],
            'external_support' => ['nullable', 'string', 'max:3000'],
            'observations' => ['nullable', 'string', 'max:5000'],
        ];
    }

    /** @return array<string, string> */
    private function baseAttributes(): array
    {
        return [
            'victims_rescued' => __('Resgatados com vida'),
            'victims_injured' => __('Feridos'),
            'victims_deceased' => __('Óbitos'),
        ];
    }

    /** @return array<string, mixed> */
    private function mapBaseData(array $validated): array
    {
        return [
            'victims_rescued' => $validated['victims_rescued'],
            'victims_injured' => $validated['victims_injured'],
            'victims_deceased' => $validated['victims_deceased'],
            'resources_summary' => $validated['resources_summary'] ?: null,
            'external_support' => $validated['external_support'] ?: null,
            'observations' => $validated['observations'] ?: null,
        ];
    }

    /** @return array<string, mixed> */
    private function validateSpecific(IncidentReportModality $modality): array
    {
        return match ($modality) {
            IncidentReportModality::FireForest => $this->validate($this->fireForestRules(), [], $this->fireForestAttributes()),
            IncidentReportModality::FireBuilding => $this->validate($this->fireBuildingRules(), [], $this->fireBuildingAttributes()),
            IncidentReportModality::RescueAnimal => $this->validate($this->rescueAnimalRules(), [], $this->rescueAnimalAttributes()),
            IncidentReportModality::RescueInsects => $this->validate($this->rescueInsectRules(), [], $this->rescueInsectAttributes()),
            IncidentReportModality::RescueOther => $this->validate($this->rescueOtherRules(), [], $this->rescueOtherAttributes()),
            default => [],
        };
    }

    /** @return array<string, mixed> */
    private function fireForestRules(): array
    {
        return [
            'ff_affected_area_ha' => ['nullable', 'numeric', 'min:0'],
            'ff_vegetation_type' => ['nullable', 'string', 'max:100'],
            'ff_fire_behavior' => ['nullable', 'string', 'max:100'],
            'ff_probable_cause' => ['nullable', 'string', 'max:100'],
            'ff_discovery_source' => ['nullable', 'string', 'max:100'],
            'ff_temperature_celsius' => ['nullable', 'integer', 'min:-60', 'max:80'],
            'ff_humidity_percent' => ['nullable', 'integer', 'min:0', 'max:100'],
            'ff_wind_speed_kmh' => ['nullable', 'integer', 'min:0'],
            'ff_wind_direction' => ['nullable', 'string', 'max:10'],
            'ff_personnel_count' => ['nullable', 'integer', 'min:0'],
            'ff_aircraft_used' => ['boolean'],
            'ff_aircraft_description' => ['nullable', 'string', 'max:500'],
            'ff_external_agencies' => ['nullable', 'array'],
            'ff_external_agencies.*' => ['integer', 'exists:operational_supports,id'],
            'ff_actions_taken' => ['nullable', 'string', 'max:3000'],
            'ff_fauna_damage' => ['boolean'],
            'ff_fauna_damage_description' => ['nullable', 'string', 'max:500'],
            'ff_structures_affected' => ['nullable', 'integer', 'min:0'],
            'ff_people_evacuated' => ['nullable', 'integer', 'min:0'],
            'ff_final_status' => ['required', 'string', 'in:extinto,controlado,monitoramento,repassado'],
        ];
    }

    /** @return array<string, string> */
    private function fireForestAttributes(): array
    {
        return [
            'ff_final_status' => __('Situação final'),
        ];
    }

    /** @return array<string, mixed> */
    private function fireBuildingRules(): array
    {
        return [
            'fb_building_type' => ['nullable', 'string', 'max:100'],
            'fb_construction_type' => ['nullable', 'string', 'max:100'],
            'fb_floors_total' => ['nullable', 'integer', 'min:1'],
            'fb_floors_affected' => ['nullable', 'integer', 'min:0'],
            'fb_affected_area_m2' => ['nullable', 'numeric', 'min:0'],
            'fb_probable_cause' => ['nullable', 'string', 'max:100'],
            'fb_fire_origin_location' => ['nullable', 'string', 'max:200'],
            'fb_hazmat_present' => ['boolean'],
            'fb_hazmat_description' => ['nullable', 'string', 'max:500'],
            'fb_occupants_at_incident' => ['nullable', 'integer', 'min:0'],
            'fb_animals_rescued' => ['nullable', 'integer', 'min:0'],
            'fb_animals_deceased' => ['nullable', 'integer', 'min:0'],
            'fb_residents_displaced' => ['nullable', 'integer', 'min:0'],
            'fb_damage_level' => ['nullable', 'string', 'in:parcial_leve,parcial_grave,total'],
            'fb_vehicle_involved' => ['boolean'],
            'fb_external_agencies' => ['nullable', 'string', 'max:1000'],
            'fb_actions_taken' => ['nullable', 'string', 'max:3000'],
            'fb_final_status' => ['required', 'string', 'in:extinto,controlado,monitoramento,transferido'],
            'fb_business_name' => ['nullable', 'string', 'max:255'],
            'fb_business_activity' => ['nullable', 'string', 'max:255'],
        ];
    }

    /** @return array<string, string> */
    private function fireBuildingAttributes(): array
    {
        return [
            'fb_final_status' => __('Situação final'),
        ];
    }

    /** Remove prefixos ff_/fb_/ra_/ri_/ro_ e mapeia para os nomes reais das colunas. */
    private function mapSpecificData(IncidentReportModality $modality, array $validated): array
    {
        return match ($modality) {
            IncidentReportModality::FireForest => $this->mapForestData($validated),
            IncidentReportModality::FireBuilding => $this->mapBuildingData($validated),
            IncidentReportModality::RescueAnimal => $this->mapAnimalData($validated),
            IncidentReportModality::RescueInsects => $this->mapInsectData($validated),
            IncidentReportModality::RescueOther => $this->mapOtherData($validated),
            default => [],
        };
    }

    /** @return array<string, mixed> */
    private function mapForestData(array $v): array
    {
        return [
            'affected_area_ha' => $v['ff_affected_area_ha'] !== '' ? $v['ff_affected_area_ha'] : null,
            'vegetation_type' => $v['ff_vegetation_type'] ?: null,
            'fire_behavior' => $v['ff_fire_behavior'] ?: null,
            'probable_cause' => $v['ff_probable_cause'] ?: null,
            'discovery_source' => $v['ff_discovery_source'] ?: null,
            'temperature_celsius' => $v['ff_temperature_celsius'] !== '' ? $v['ff_temperature_celsius'] : null,
            'humidity_percent' => $v['ff_humidity_percent'] !== '' ? $v['ff_humidity_percent'] : null,
            'wind_speed_kmh' => $v['ff_wind_speed_kmh'] !== '' ? $v['ff_wind_speed_kmh'] : null,
            'wind_direction' => $v['ff_wind_direction'] ?: null,
            'personnel_count' => $v['ff_personnel_count'] ?: null,
            'aircraft_used' => $v['ff_aircraft_used'],
            'aircraft_description' => $v['ff_aircraft_description'] ?: null,
            'external_agencies' => $v['ff_external_agencies'] ?: null,
            'actions_taken' => $v['ff_actions_taken'] ?: null,
            'fauna_damage' => $v['ff_fauna_damage'],
            'fauna_damage_description' => $v['ff_fauna_damage_description'] ?: null,
            'structures_affected' => $v['ff_structures_affected'] ?? 0,
            'people_evacuated' => $v['ff_people_evacuated'] ?? 0,
            'final_status' => $v['ff_final_status'],
        ];
    }

    /** @return array<string, mixed> */
    private function mapBuildingData(array $v): array
    {
        return [
            'building_type' => $v['fb_building_type'] ?: null,
            'construction_type' => $v['fb_construction_type'] ?: null,
            'floors_total' => $v['fb_floors_total'] !== '' ? $v['fb_floors_total'] : null,
            'floors_affected' => $v['fb_floors_affected'] !== '' ? $v['fb_floors_affected'] : null,
            'affected_area_m2' => $v['fb_affected_area_m2'] !== '' ? $v['fb_affected_area_m2'] : null,
            'probable_cause' => $v['fb_probable_cause'] ?: null,
            'fire_origin_location' => $v['fb_fire_origin_location'] ?: null,
            'hazmat_present' => $v['fb_hazmat_present'],
            'hazmat_description' => $v['fb_hazmat_description'] ?: null,
            'occupants_at_incident' => $v['fb_occupants_at_incident'] !== '' ? $v['fb_occupants_at_incident'] : null,
            'animals_rescued' => $v['fb_animals_rescued'] ?? 0,
            'animals_deceased' => $v['fb_animals_deceased'] ?? 0,
            'residents_displaced' => $v['fb_residents_displaced'] ?? 0,
            'damage_level' => $v['fb_damage_level'] ?: null,
            'vehicle_involved' => $v['fb_vehicle_involved'],
            'external_agencies' => $v['fb_external_agencies'] ?: null,
            'actions_taken' => $v['fb_actions_taken'] ?: null,
            'final_status' => $v['fb_final_status'],
            'business_name' => $v['fb_business_name'] ?: null,
            'business_activity' => $v['fb_business_activity'] ?: null,
        ];
    }

    // ── Salvamento animal ────────────────────────────────────────────────────

    /** @return array<string, mixed> */
    private function rescueAnimalRules(): array
    {
        return [
            'ra_animal_category' => ['required', 'string', 'in:domestico,silvestre,de_producao'],
            'ra_animal_species' => ['required', 'string', 'max:100'],
            'ra_animal_breed' => ['nullable', 'string', 'max:100'],
            'ra_animal_size' => ['nullable', 'string', 'in:pequeno,medio,grande'],
            'ra_entrapment_type' => ['required', 'string', 'max:100'],
            'ra_entrapment_height_m' => ['nullable', 'integer', 'min:0', 'max:500'],
            'ra_animal_condition_arrival' => ['required', 'string', 'in:calmo,agitado,ferido,inconsciente,obito_chegada'],
            'ra_equipment_used' => ['nullable', 'string', 'max:2000'],
            'ra_outcome' => ['required', 'string', 'in:resgatado_tutor,resgatado_abrigo,resgatado_veterinario,solto_silvestre,nao_localizado,obito'],
            'ra_owner_name' => ['nullable', 'string', 'max:255'],
            'ra_owner_phone' => ['nullable', 'string', 'max:30'],
            'ra_destination_notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /** @return array<string, string> */
    private function rescueAnimalAttributes(): array
    {
        return [
            'ra_animal_category' => __('Categoria do animal'),
            'ra_animal_species' => __('Espécie'),
            'ra_entrapment_type' => __('Tipo de aprisionamento'),
            'ra_animal_condition_arrival' => __('Condição na chegada'),
            'ra_outcome' => __('Desfecho'),
        ];
    }

    /** @return array<string, mixed> */
    private function mapAnimalData(array $v): array
    {
        return [
            'animal_category' => $v['ra_animal_category'],
            'animal_species' => $v['ra_animal_species'],
            'animal_breed' => $v['ra_animal_breed'] ?: null,
            'animal_size' => $v['ra_animal_size'] ?: null,
            'entrapment_type' => $v['ra_entrapment_type'],
            'entrapment_height_m' => $v['ra_entrapment_height_m'] !== '' ? $v['ra_entrapment_height_m'] : null,
            'animal_condition_arrival' => $v['ra_animal_condition_arrival'],
            'equipment_used' => $v['ra_equipment_used'] ?: null,
            'outcome' => $v['ra_outcome'],
            'owner_name' => $v['ra_owner_name'] ?: null,
            'owner_phone' => $v['ra_owner_phone'] ?: null,
            'destination_notes' => $v['ra_destination_notes'] ?: null,
        ];
    }

    // ── Insetos agressivos ───────────────────────────────────────────────────

    /** @return array<string, mixed> */
    private function rescueInsectRules(): array
    {
        return [
            'ri_insect_type' => ['required', 'string', 'in:abelhas,marimbondos,vespas,maribondo_tatu,outro'],
            'ri_insect_species' => ['nullable', 'string', 'max:100'],
            'ri_colony_size_estimate' => ['nullable', 'string', 'in:pequena,media,grande,indeterminada'],
            'ri_nest_location_type' => ['required', 'string', 'max:100'],
            'ri_nest_location_detail' => ['nullable', 'string', 'max:500'],
            'ri_technique_used' => ['required', 'string', 'in:captura_realocacao,exterminacao_quimica,exterminacao_fisica,nao_realizado'],
            'ri_colony_destination' => ['nullable', 'string', 'in:apicultor,exterminada,realocada,abandono_local'],
            'ri_people_stung' => ['required', 'integer', 'min:0'],
            'ri_sting_severity' => ['nullable', 'string', 'in:sem_atendimento,leve,moderado_hospitalar,grave'],
            'ri_prehospital_care' => ['boolean'],
            'ri_prehospital_description' => ['nullable', 'string', 'max:1000'],
            'ri_equipment_used' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /** @return array<string, string> */
    private function rescueInsectAttributes(): array
    {
        return [
            'ri_insect_type' => __('Tipo de inseto'),
            'ri_nest_location_type' => __('Local do ninho'),
            'ri_technique_used' => __('Técnica utilizada'),
        ];
    }

    /** @return array<string, mixed> */
    private function mapInsectData(array $v): array
    {
        return [
            'insect_type' => $v['ri_insect_type'],
            'insect_species' => $v['ri_insect_species'] ?: null,
            'colony_size_estimate' => $v['ri_colony_size_estimate'] ?: null,
            'nest_location_type' => $v['ri_nest_location_type'],
            'nest_location_detail' => $v['ri_nest_location_detail'] ?: null,
            'technique_used' => $v['ri_technique_used'],
            'colony_destination' => $v['ri_colony_destination'] ?: null,
            'people_stung' => $v['ri_people_stung'],
            'sting_severity' => $v['ri_sting_severity'] ?: null,
            'prehospital_care' => $v['ri_prehospital_care'],
            'prehospital_description' => $v['ri_prehospital_description'] ?: null,
            'equipment_used' => $v['ri_equipment_used'] ?: null,
        ];
    }

    // ── Outros salvamentos ───────────────────────────────────────────────────

    /** @return array<string, mixed> */
    private function rescueOtherRules(): array
    {
        return [
            'ro_rescue_subtype' => ['required', 'string', 'in:aquatico,altura,colapso_estrutural,desencarceramento,espaco_confinado,elevador,outro'],
            'ro_victim_count' => ['required', 'integer', 'min:1'],
            'ro_situation_description' => ['required', 'string', 'max:3000'],
            'ro_victim_condition' => ['required', 'string', 'in:ileso,ferido_leve,ferido_grave,obito'],
            'ro_entrapment_description' => ['nullable', 'string', 'max:2000'],
            'ro_rescue_technique' => ['required', 'string', 'max:2000'],
            'ro_equipment_used' => ['nullable', 'string', 'max:2000'],
            'ro_hospital_transport' => ['boolean'],
            'ro_hospital_name' => ['nullable', 'string', 'max:255'],
            'ro_outcome' => ['required', 'string', 'in:resgatado_ileso,resgatado_ferido,obito_local,nao_localizado'],
            'ro_duration_minutes' => ['nullable', 'integer', 'min:1'],
        ];
    }

    /** @return array<string, string> */
    private function rescueOtherAttributes(): array
    {
        return [
            'ro_rescue_subtype' => __('Subtipo de salvamento'),
            'ro_situation_description' => __('Descrição da situação'),
            'ro_victim_condition' => __('Condição da vítima'),
            'ro_rescue_technique' => __('Técnica de salvamento'),
            'ro_outcome' => __('Desfecho'),
        ];
    }

    /** @return array<string, mixed> */
    private function mapOtherData(array $v): array
    {
        return [
            'rescue_subtype' => $v['ro_rescue_subtype'],
            'victim_count' => $v['ro_victim_count'],
            'situation_description' => $v['ro_situation_description'],
            'victim_condition' => $v['ro_victim_condition'],
            'entrapment_description' => $v['ro_entrapment_description'] ?: null,
            'rescue_technique' => $v['ro_rescue_technique'],
            'equipment_used' => $v['ro_equipment_used'] ?: null,
            'hospital_transport' => $v['ro_hospital_transport'],
            'hospital_name' => $v['ro_hospital_name'] ?: null,
            'outcome' => $v['ro_outcome'],
            'duration_minutes' => $v['ro_duration_minutes'] !== '' ? $v['ro_duration_minutes'] : null,
        ];
    }
}
