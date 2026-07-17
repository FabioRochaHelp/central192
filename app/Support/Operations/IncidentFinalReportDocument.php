<?php

declare(strict_types=1);

namespace App\Support\Operations;

use App\Domain\Fire\Enums\FireScarStatus;
use App\Domain\Operations\Enums\IncidentReportModality;
use App\Models\FireBuildingReport;
use App\Models\FireForestReport;
use App\Models\FireScarAnalysis;
use App\Models\Incident;
use App\Models\IncidentEvent;
use App\Models\IncidentFinalReport;
use App\Models\OperationalSupport;
use App\Models\RescueAnimalReport;
use App\Models\RescueInsectReport;
use App\Models\RescueOtherReport;
use App\Models\Vehicle;
use Illuminate\Support\Collection;

/** Monta os dados consolidados do relatório final CB para impressão/download. */
final class IncidentFinalReportDocument
{
    public function __construct(
        public readonly Incident $incident,
        public readonly IncidentFinalReport $report,
    ) {}

    public static function fromIncident(Incident $incident): self
    {
        $incident->loadMissing([
            'nature',
            'municipio',
            'operationalCallAlerts',
            'finalReport.filledBy',
            'finalReport.fireForestReport',
            'finalReport.fireBuildingReport',
            'finalReport.rescueAnimalReport',
            'finalReport.rescueInsectReport',
            'finalReport.rescueOtherReport',
            'dispatches' => fn ($query) => $query->withTrashed()->with(['shift.vehicle', 'shift.staff']),
        ]);

        $report = $incident->finalReport;
        abort_unless($report instanceof IncidentFinalReport, 404);

        return new self($incident, $report);
    }

    public function modality(): IncidentReportModality
    {
        return $this->report->modality;
    }

    public function title(): string
    {
        return __('Relatório final — :modality', [
            'modality' => $this->modality()->label(),
        ]);
    }

    public function talaoLabel(): string
    {
        return __('Talão :talao/:ano', [
            'talao' => $this->incident->talao ?? '—',
            'ano' => $this->incident->dispatch_year,
        ]);
    }

    public function addressLine(): string
    {
        $parts = array_filter([
            $this->incident->address_line,
            $this->incident->number,
            $this->incident->district,
            $this->incident->city,
        ]);

        return $parts !== [] ? implode(', ', $parts) : '—';
    }

    /** @return list<array{label: string, value: string}> */
    public function incidentSummary(): array
    {
        return [
            ['label' => __('Natureza'), 'value' => $this->incident->nature?->name ?? '—'],
            ['label' => __('Status da ocorrência'), 'value' => $this->incident->status->label()],
            ['label' => __('Data/hora'), 'value' => $this->incident->occurred_at?->format('d/m/Y H:i:s') ?? '—'],
            ['label' => __('Solicitante'), 'value' => trim(($this->incident->caller_name ?? '—').' · '.($this->incident->caller_phone ?? '—'))],
        ];
    }

    public function hasCallAlertData(): bool
    {
        return $this->callAlertFireReport() !== null;
    }

    /** @return array<string, mixed>|null */
    public function callAlertFireReport(): ?array
    {
        return once(fn (): ?array => OperationalCallAlertFireReport::fromIncident($this->incident));
    }

    /** @return list<array{label: string, value: string}> */
    public function callAlertFields(): array
    {
        $report = $this->callAlertFireReport();
        if ($report === null) {
            return [];
        }

        $fields = [
            ['label' => __('Alertas vinculados'), 'value' => (string) $report['alert_count']],
        ];

        if ($report['reference'] !== null && $report['reference'] !== '') {
            $fields[] = ['label' => __('Referência externa'), 'value' => (string) $report['reference']];
        }

        if ($report['caller_name'] !== null && $report['caller_name'] !== '') {
            $fields[] = ['label' => __('Solicitante do alerta'), 'value' => (string) $report['caller_name']];
        }

        if ($report['period']['from'] !== null && $report['period']['to'] !== null) {
            $fields[] = [
                'label' => __('Período monitorado'),
                'value' => $report['period']['from'].' – '.$report['period']['to'],
            ];
        }

        foreach ($report['metrics'] as $metric) {
            $fields[] = [
                'label' => (string) $metric['label'],
                'value' => (string) $metric['value'],
            ];
        }

        $fields[] = [
            'label' => __('Risco estimado (sensor)'),
            'value' => $report['risk']['label'].' ('.$report['risk']['score'].')',
        ];

        return $fields;
    }

    /** @return list<string> */
    public function callAlertObservationTexts(): array
    {
        $report = $this->callAlertFireReport();
        if ($report === null) {
            return [];
        }

        return array_map(
            fn (array $observation): string => $observation['title'].': '.$observation['text'],
            $report['observations'],
        );
    }

    public function hasDispatchContactAttempts(): bool
    {
        return $this->dispatchContactAttempts() !== [];
    }

    /**
     * Registros de contato pré-despacho (antes do empenho da viatura), em ordem cronológica.
     *
     * @return list<array{result: string, successful: bool, method: string, details: string, vehicle: string, vehicle_base: string, reason: string, recorded_at: string, actor: string}>
     */
    public function dispatchContactAttempts(): array
    {
        return once(function (): array {
            $events = IncidentEvent::query()
                ->withoutGlobalScopes()
                ->with('actor')
                ->where('incident_id', $this->incident->id)
                ->whereIn('event_key', ['dispatch_contact_attempted', 'dispatch_contact_failed'])
                ->orderBy('recorded_at')
                ->get();

            if ($events->isEmpty()) {
                return [];
            }

            $vehicleIds = $events
                ->map(fn (IncidentEvent $event): mixed => $event->payload['vehicle_id'] ?? null)
                ->filter()
                ->unique()
                ->values();

            $vehicles = $vehicleIds->isEmpty()
                ? collect()
                : Vehicle::query()
                    ->withoutGlobalScopes()
                    ->with('municipio')
                    ->whereIn('id', $vehicleIds)
                    ->get()
                    ->keyBy('id');

            return $events->map(function (IncidentEvent $event) use ($vehicles): array {
                $payload = $event->payload ?? [];
                $successful = (bool) ($payload['successful'] ?? false);
                $vehicleId = $payload['vehicle_id'] ?? null;
                $vehicle = $vehicleId !== null ? $vehicles->get($vehicleId) : null;

                return [
                    'result' => $successful ? __('Contato efetuado') : __('Não foi possível contatar'),
                    'successful' => $successful,
                    'method' => $this->contactMethodLabel((string) ($payload['contact_method'] ?? '')),
                    'details' => ((string) ($payload['contact_details'] ?? '')) ?: '—',
                    'vehicle' => $vehicle?->prefix
                        ?? ($vehicleId !== null ? __('Viatura #:id', ['id' => $vehicleId]) : '—'),
                    'vehicle_base' => $vehicle?->municipio?->razao_social ?? '—',
                    'reason' => ((string) ($payload['reason'] ?? '')) ?: '—',
                    'recorded_at' => $event->recorded_at?->format('d/m/Y H:i') ?? '—',
                    'actor' => $event->actor?->name ?? '—',
                ];
            })->all();
        });
    }

    private function contactMethodLabel(string $method): string
    {
        return match ($method) {
            'ramal' => __('Ramal'),
            'telefone' => __('Telefone'),
            'whatsapp' => __('WhatsApp'),
            default => $method !== '' ? $method : '—',
        };
    }

    /** @return list<array{vehicle: string, shift_period: string, staff: list<string>, stage: string}> */
    public function dispatchUnits(): array
    {
        return $this->incident->dispatches
            ->sortBy('id')
            ->map(function ($dispatch): array {
                $shift = $dispatch->shift;
                $vehicle = $shift?->vehicle;

                $vehicleLabel = $vehicle !== null
                    ? trim(sprintf('%s · %s', $vehicle->prefix, $vehicle->plate ?? __('Sem placa')))
                    : __('Viatura não informada');

                $shiftPeriod = $shift !== null
                    ? sprintf('%s – %s', $shift->starts_at->format('d/m/Y H:i'), $shift->ends_at->format('d/m/Y H:i'))
                    : '—';

                $staff = $shift?->staff
                    ->map(fn ($member) => trim(sprintf(
                        '%s%s',
                        $member->name,
                        $member->cargo ? ' ('.$member->cargo->label().')' : '',
                    )))->values()->all() ?? [];

                return [
                    'vehicle' => $vehicleLabel,
                    'shift_period' => $shiftPeriod,
                    'staff' => $staff,
                    'stage' => $dispatch->stage->label(),
                ];
            })
            ->values()
            ->all();
    }

    /** @return list<array{label: string, value: string}> */
    public function baseFields(): array
    {
        return [
            ['label' => __('Resgatados com vida'), 'value' => (string) $this->report->victims_rescued],
            ['label' => __('Feridos'), 'value' => (string) $this->report->victims_injured],
            ['label' => __('Óbitos confirmados'), 'value' => (string) $this->report->victims_deceased],
            ['label' => __('Resumo de recursos empregados'), 'value' => $this->report->resources_summary ?: '—'],
            ['label' => __('Apoios externos acionados'), 'value' => $this->report->external_support ?: '—'],
            ['label' => __('Observações gerais'), 'value' => $this->report->observations ?: '—'],
            ['label' => __('Preenchido por'), 'value' => $this->report->filledBy?->name ?? '—'],
            ['label' => __('Data do relatório'), 'value' => $this->report->submitted_at?->format('d/m/Y H:i') ?? '—'],
        ];
    }

    public function outcomeLabel(): string
    {
        $specific = $this->specificOutcomeValue();

        if ($specific !== null) {
            return FinalReportFieldLabels::format('final_status', $specific);
        }

        return $this->incident->status->label();
    }

    /** @return list<array{label: string, value: string}> */
    public function specificFields(): array
    {
        return match ($this->modality()) {
            IncidentReportModality::FireForest => $this->fireForestFields($this->report->fireForestReport),
            IncidentReportModality::FireBuilding => $this->fireBuildingFields($this->report->fireBuildingReport),
            IncidentReportModality::RescueAnimal => $this->rescueAnimalFields($this->report->rescueAnimalReport),
            IncidentReportModality::RescueInsects => $this->rescueInsectFields($this->report->rescueInsectReport),
            IncidentReportModality::RescueOther => $this->rescueOtherFields($this->report->rescueOtherReport),
            default => [],
        };
    }

    public function hasFireScars(): bool
    {
        return $this->modality() === IncidentReportModality::FireForest && $this->fireScars() !== [];
    }

    /**
     * Análises de cicatriz concluídas vinculadas à ocorrência (só incêndio florestal).
     * Cada item é uma lista de campos compatível com o partial de tabela de campos.
     *
     * @return list<list<array{label: string, value: string}>>
     */
    public function fireScars(): array
    {
        if ($this->modality() !== IncidentReportModality::FireForest) {
            return [];
        }

        return once(fn (): array => FireScarAnalysis::query()
            ->where('incident_id', $this->incident->id)
            ->where('status', FireScarStatus::Concluido->value)
            ->latest('id')
            ->get()
            ->map(fn (FireScarAnalysis $scar): array => [
                ['label' => __('Severidade'), 'value' => $scar->severity()?->label() ?? '—'],
                ['label' => __('Área queimada'), 'value' => $scar->area_ha !== null ? number_format((float) $scar->area_ha, 2, ',', '.').' ha' : '—'],
                ['label' => __('Perímetro'), 'value' => $scar->perimeter_km !== null ? number_format((float) $scar->perimeter_km, 2, ',', '.').' km' : '—'],
                ['label' => __('Confiança'), 'value' => $scar->confidence ?? '—'],
                ['label' => __('dNBR'), 'value' => $scar->dnbr !== null ? number_format((float) $scar->dnbr, 4, ',', '.') : '—'],
                ['label' => __('Pré/pós-fogo'), 'value' => ($scar->pre_fire_date?->format('d/m/Y') ?? '—').' → '.($scar->post_fire_date?->format('d/m/Y') ?? '—')],
            ])
            ->all());
    }

    private function specificOutcomeValue(): ?string
    {
        return match ($this->modality()) {
            IncidentReportModality::FireForest => $this->report->fireForestReport?->final_status,
            IncidentReportModality::FireBuilding => $this->report->fireBuildingReport?->final_status,
            IncidentReportModality::RescueAnimal => $this->report->rescueAnimalReport?->outcome,
            IncidentReportModality::RescueInsects => $this->report->rescueInsectReport?->colony_destination
                ?? $this->report->rescueInsectReport?->technique_used,
            IncidentReportModality::RescueOther => $this->report->rescueOtherReport?->outcome,
            default => null,
        };
    }

    /** @return list<array{label: string, value: string}> */
    private function fireForestFields(?FireForestReport $forest): array
    {
        if ($forest === null) {
            return [];
        }

        $externalAgencies = $this->resolveSupportNames($forest->external_agencies);

        return [
            ['label' => __('Área atingida (ha)'), 'value' => $forest->affected_area_ha !== null ? (string) $forest->affected_area_ha : '—'],
            ['label' => __('Tipo de vegetação'), 'value' => FinalReportFieldLabels::format('vegetation_type', $forest->vegetation_type)],
            ['label' => __('Comportamento do fogo'), 'value' => FinalReportFieldLabels::format('fire_behavior', $forest->fire_behavior)],
            ['label' => __('Causa provável'), 'value' => FinalReportFieldLabels::format('probable_cause', $forest->probable_cause)],
            ['label' => __('Fonte de descoberta'), 'value' => FinalReportFieldLabels::format('discovery_source', $forest->discovery_source)],
            ['label' => __('Situação final'), 'value' => FinalReportFieldLabels::format('final_status', $forest->final_status)],
            ['label' => __('Temperatura (°C)'), 'value' => $forest->temperature_celsius !== null ? (string) $forest->temperature_celsius : '—'],
            ['label' => __('Umidade (%)'), 'value' => $forest->humidity_percent !== null ? (string) $forest->humidity_percent : '—'],
            ['label' => __('Vel. vento (km/h)'), 'value' => $forest->wind_speed_kmh !== null ? (string) $forest->wind_speed_kmh : '—'],
            ['label' => __('Direção do vento'), 'value' => $forest->wind_direction ?: '—'],
            ['label' => __('Efetivo empregado'), 'value' => $forest->personnel_count !== null ? (string) $forest->personnel_count : '—'],
            ['label' => __('Estruturas atingidas'), 'value' => (string) ($forest->structures_affected ?? 0)],
            ['label' => __('Pessoas evacuadas'), 'value' => (string) ($forest->people_evacuated ?? 0)],
            ['label' => __('Aeronave utilizada'), 'value' => FinalReportFieldLabels::format(null, $forest->aircraft_used)],
            ['label' => __('Descrição da aeronave'), 'value' => $forest->aircraft_description ?: '—'],
            ['label' => __('Dano à fauna'), 'value' => FinalReportFieldLabels::format(null, $forest->fauna_damage)],
            ['label' => __('Descrição do dano à fauna'), 'value' => $forest->fauna_damage_description ?: '—'],
            ['label' => __('Apoios externos'), 'value' => $externalAgencies],
            ['label' => __('Ações realizadas'), 'value' => $forest->actions_taken ?: '—'],
        ];
    }

    /** @return list<array{label: string, value: string}> */
    private function fireBuildingFields(?FireBuildingReport $building): array
    {
        if ($building === null) {
            return [];
        }

        return [
            ['label' => __('Tipo de edificação'), 'value' => FinalReportFieldLabels::format('building_type', $building->building_type)],
            ['label' => __('Tipo de construção'), 'value' => FinalReportFieldLabels::format('construction_type', $building->construction_type)],
            ['label' => __('Situação final'), 'value' => FinalReportFieldLabels::format('final_status', $building->final_status)],
            ['label' => __('Andares totais'), 'value' => $building->floors_total !== null ? (string) $building->floors_total : '—'],
            ['label' => __('Andares atingidos'), 'value' => $building->floors_affected !== null ? (string) $building->floors_affected : '—'],
            ['label' => __('Área atingida (m²)'), 'value' => $building->affected_area_m2 !== null ? (string) $building->affected_area_m2 : '—'],
            ['label' => __('Causa provável'), 'value' => FinalReportFieldLabels::format('probable_cause', $building->probable_cause)],
            ['label' => __('Origem do incêndio'), 'value' => $building->fire_origin_location ?: '—'],
            ['label' => __('Grau de dano'), 'value' => FinalReportFieldLabels::format('damage_level', $building->damage_level)],
            ['label' => __('Ocupantes presentes'), 'value' => $building->occupants_at_incident !== null ? (string) $building->occupants_at_incident : '—'],
            ['label' => __('Animais resgatados'), 'value' => (string) ($building->animals_rescued ?? 0)],
            ['label' => __('Animais mortos'), 'value' => (string) ($building->animals_deceased ?? 0)],
            ['label' => __('Desabrigados'), 'value' => (string) ($building->residents_displaced ?? 0)],
            ['label' => __('Produtos perigosos presentes'), 'value' => FinalReportFieldLabels::format(null, $building->hazmat_present)],
            ['label' => __('Descrição dos produtos perigosos'), 'value' => $building->hazmat_description ?: '—'],
            ['label' => __('Veículo envolvido'), 'value' => FinalReportFieldLabels::format(null, $building->vehicle_involved)],
            ['label' => __('Razão social / estabelecimento'), 'value' => $building->business_name ?: '—'],
            ['label' => __('Ramo de atividade'), 'value' => $building->business_activity ?: '—'],
            ['label' => __('Agências externas'), 'value' => $building->external_agencies ?: '—'],
            ['label' => __('Ações realizadas'), 'value' => $building->actions_taken ?: '—'],
        ];
    }

    /** @return list<array{label: string, value: string}> */
    private function rescueAnimalFields(?RescueAnimalReport $animal): array
    {
        if ($animal === null) {
            return [];
        }

        return [
            ['label' => __('Categoria'), 'value' => FinalReportFieldLabels::format('animal_category', $animal->animal_category)],
            ['label' => __('Espécie'), 'value' => FinalReportFieldLabels::format('animal_species', $animal->animal_species)],
            ['label' => __('Raça / descrição'), 'value' => $animal->animal_breed ?: '—'],
            ['label' => __('Porte'), 'value' => FinalReportFieldLabels::format('animal_size', $animal->animal_size)],
            ['label' => __('Tipo de aprisionamento'), 'value' => FinalReportFieldLabels::format('entrapment_type', $animal->entrapment_type)],
            ['label' => __('Altura (m)'), 'value' => $animal->entrapment_height_m !== null ? (string) $animal->entrapment_height_m : '—'],
            ['label' => __('Condição na chegada'), 'value' => FinalReportFieldLabels::format('animal_condition_arrival', $animal->animal_condition_arrival)],
            ['label' => __('Equipamentos utilizados'), 'value' => $animal->equipment_used ?: '—'],
            ['label' => __('Desfecho'), 'value' => FinalReportFieldLabels::format('outcome', $animal->outcome)],
            ['label' => __('Tutor / responsável'), 'value' => $animal->owner_name ?: '—'],
            ['label' => __('Telefone do tutor'), 'value' => $animal->owner_phone ?: '—'],
            ['label' => __('Destino / observações'), 'value' => $animal->destination_notes ?: '—'],
        ];
    }

    /** @return list<array{label: string, value: string}> */
    private function rescueInsectFields(?RescueInsectReport $insect): array
    {
        if ($insect === null) {
            return [];
        }

        return [
            ['label' => __('Tipo de inseto'), 'value' => FinalReportFieldLabels::format('insect_type', $insect->insect_type)],
            ['label' => __('Espécie'), 'value' => $insect->insect_species ?: '—'],
            ['label' => __('Tamanho estimado da colônia'), 'value' => FinalReportFieldLabels::format('colony_size_estimate', $insect->colony_size_estimate)],
            ['label' => __('Local do ninho'), 'value' => FinalReportFieldLabels::format(null, $insect->nest_location_type)],
            ['label' => __('Detalhe do local'), 'value' => $insect->nest_location_detail ?: '—'],
            ['label' => __('Técnica utilizada'), 'value' => FinalReportFieldLabels::format('technique_used', $insect->technique_used)],
            ['label' => __('Destino da colônia'), 'value' => FinalReportFieldLabels::format('colony_destination', $insect->colony_destination)],
            ['label' => __('Pessoas picadas'), 'value' => (string) $insect->people_stung],
            ['label' => __('Gravidade das picadas'), 'value' => FinalReportFieldLabels::format('sting_severity', $insect->sting_severity)],
            ['label' => __('Atendimento pré-hospitalar'), 'value' => FinalReportFieldLabels::format(null, $insect->prehospital_care)],
            ['label' => __('Descrição do atendimento'), 'value' => $insect->prehospital_description ?: '—'],
            ['label' => __('Equipamentos utilizados'), 'value' => $insect->equipment_used ?: '—'],
        ];
    }

    /** @return list<array{label: string, value: string}> */
    private function rescueOtherFields(?RescueOtherReport $other): array
    {
        if ($other === null) {
            return [];
        }

        return [
            ['label' => __('Subtipo de salvamento'), 'value' => FinalReportFieldLabels::format('rescue_subtype', $other->rescue_subtype)],
            ['label' => __('Quantidade de vítimas'), 'value' => (string) $other->victim_count],
            ['label' => __('Descrição da situação'), 'value' => $other->situation_description ?: '—'],
            ['label' => __('Condição da vítima'), 'value' => FinalReportFieldLabels::format('victim_condition', $other->victim_condition)],
            ['label' => __('Descrição do aprisionamento'), 'value' => $other->entrapment_description ?: '—'],
            ['label' => __('Técnica de salvamento'), 'value' => $other->rescue_technique ?: '—'],
            ['label' => __('Equipamentos utilizados'), 'value' => $other->equipment_used ?: '—'],
            ['label' => __('Transporte hospitalar'), 'value' => FinalReportFieldLabels::format(null, $other->hospital_transport)],
            ['label' => __('Hospital de destino'), 'value' => $other->hospital_name ?: '—'],
            ['label' => __('Desfecho'), 'value' => FinalReportFieldLabels::format('outcome', $other->outcome)],
            ['label' => __('Duração (minutos)'), 'value' => $other->duration_minutes !== null ? (string) $other->duration_minutes : '—'],
        ];
    }

    /** @param  array<int>|null  $ids */
    private function resolveSupportNames(?array $ids): string
    {
        if ($ids === null || $ids === []) {
            return '—';
        }

        /** @var Collection<int, OperationalSupport> $supports */
        $supports = OperationalSupport::query()->whereIn('id', $ids)->orderBy('name')->pluck('name');

        return $supports->isEmpty() ? '—' : $supports->implode(', ');
    }
}
