<?php

declare(strict_types=1);

use App\Domain\Operations\Enums\DispatchStage;
use App\Domain\Operations\Enums\IncidentReportModality;
use App\Domain\Operations\Enums\IncidentStatus;
use App\Domain\Operations\Enums\ShiftStatus;
use App\Domain\Operations\Enums\StaffCargo;
use App\Models\FireForestReport;
use App\Models\Incident;
use App\Models\IncidentDispatch;
use App\Models\IncidentFinalReport;
use App\Models\Municipio;
use App\Models\Nature;
use App\Models\OperationalCallAlert;
use App\Models\Shift;
use App\Models\Staff;
use App\Models\User;
use App\Models\Vehicle;
use Database\Seeders\FireModalitySeeder;
use Database\Seeders\OperationalDemoSeeder;

beforeEach(function (): void {
    $this->seed(OperationalDemoSeeder::class);
    $this->seed(FireModalitySeeder::class);
});

function createClosedFireForestIncidentForDocumentTests(): Incident
{
    /** @var Municipio $municipio */
    $municipio = Municipio::query()->firstOrFail();

    $nature = Nature::query()
        ->whereHas('natureType', fn ($query) => $query->where('name', 'Incêndio'))
        ->where('report_modality', IncidentReportModality::FireForest->value)
        ->firstOrFail();

    $vehicle = Vehicle::query()->where('municipio_id', $municipio->id)->firstOrFail();

    $shift = Shift::query()->create([
        'municipio_id' => $municipio->id,
        'vehicle_id' => $vehicle->id,
        'starts_at' => now()->subHours(4),
        'ends_at' => now()->addHours(8),
        'status' => ShiftStatus::Disponivel,
        'status_legacy' => 1,
    ]);

    $staff = Staff::query()->create([
        'municipio_id' => $municipio->id,
        'name' => 'Brigadista Teste',
        'document_type' => null,
        'document_number' => 'DOC-001',
        'cpf' => null,
        'email' => null,
        'phone' => null,
        'cargo' => StaffCargo::Brigadista->value,
    ]);

    $shift->staff()->attach($staff->id);

    $incident = Incident::query()->create([
        'municipio_id' => $municipio->id,
        'dispatch_year' => (int) now()->format('Y'),
        'talao' => 880000 + random_int(1, 999),
        'status' => IncidentStatus::Closed,
        'nature_id' => $nature->id,
        'occurred_at' => now()->subHours(3),
        'address_line' => 'Estrada Rural',
        'city' => 'Demonstração',
        'description' => 'Incêndio em área de preservação.',
        'caller_phone' => '11999990000',
    ]);

    $dispatch = IncidentDispatch::query()->create([
        'municipio_id' => $municipio->id,
        'incident_id' => $incident->id,
        'shift_id' => $shift->id,
        'stage' => DispatchStage::LeftScene,
    ]);

    $dispatch->delete();

    $report = IncidentFinalReport::query()->create([
        'incident_id' => $incident->id,
        'user_id' => User::query()->where('email', 'enfermeiro@example.com')->value('id'),
        'modality' => IncidentReportModality::FireForest,
        'victims_rescued' => 0,
        'victims_injured' => 0,
        'victims_deceased' => 0,
        'resources_summary' => 'UAT, EPI completo.',
        'submitted_at' => now(),
    ]);

    FireForestReport::query()->create([
        'incident_final_report_id' => $report->id,
        'fire_behavior' => 'copa',
        'final_status' => 'extinto',
        'personnel_count' => 6,
        'actions_taken' => 'Aceiro e abafamento.',
    ]);

    OperationalCallAlert::factory()->converted()->create([
        'converted_incident_id' => $incident->id,
        'latitude' => -23.55012,
        'longitude' => -46.63045,
        'external_reference' => 'SAT-FINAL-01',
        'metadata' => [
            'temperature' => 305.0,
            'humidity' => 28.0,
            'wind_speed' => 22.0,
            'wind_direction_text' => 'NE',
        ],
    ]);

    return $incident->fresh(['nature', 'finalReport.fireForestReport', 'operationalCallAlerts']);
}

test('nurse report is not available for cb fire forest incidents', function (): void {
    $incident = Incident::query()->create([
        'municipio_id' => Municipio::query()->value('id'),
        'dispatch_year' => (int) now()->format('Y'),
        'talao' => 881000 + random_int(1, 999),
        'status' => IncidentStatus::PendingFinalReport,
        'nature_id' => Nature::query()
            ->where('report_modality', IncidentReportModality::FireForest->value)
            ->value('id'),
        'occurred_at' => now(),
        'caller_phone' => '11999991111',
    ]);

    /** @var User $nurse */
    $nurse = User::query()->where('email', 'enfermeiro@example.com')->firstOrFail();

    $this->actingAs($nurse)
        ->get(route('operations.incidents.nurse-report', $incident))
        ->assertForbidden();
});

test('final report document shows outcome vehicle staff and fire behavior', function (): void {
    $incident = createClosedFireForestIncidentForDocumentTests();

    /** @var User $nurse */
    $nurse = User::query()->where('email', 'enfermeiro@example.com')->firstOrFail();

    $response = $this->actingAs($nurse)
        ->get(route('operations.incidents.final-report.document', $incident));

    $response->assertOk()
        ->assertSee('Comportamento do fogo')
        ->assertSee('Copa')
        ->assertSee('Desfecho da ocorrência')
        ->assertSee('Extinto')
        ->assertSee('Brigadista Teste')
        ->assertSee('US 01')
        ->assertSee('Aceiro e abafamento.')
        ->assertSee('Alerta operacional — comportamento do fogo (sensor)')
        ->assertSee('SAT-FINAL-01');
});

test('final report document download returns pdf attachment', function (): void {
    $incident = createClosedFireForestIncidentForDocumentTests();

    /** @var User $nurse */
    $nurse = User::query()->where('email', 'enfermeiro@example.com')->firstOrFail();

    $response = $this->actingAs($nurse)
        ->get(route('operations.incidents.final-report.document', ['incident' => $incident, 'download' => 1]));

    $response->assertOk()
        ->assertHeader('content-type', 'application/pdf');

    expect($response->headers->get('content-disposition'))
        ->toContain('attachment')
        ->toContain('relatorio-final-'.$incident->talao.'-'.$incident->dispatch_year.'.pdf');

    expect(str_starts_with((string) $response->getContent(), '%PDF'))->toBeTrue();
});

test('final report document is forbidden without saved report', function (): void {
    /** @var Municipio $municipio */
    $municipio = Municipio::query()->firstOrFail();

    $incident = Incident::query()->create([
        'municipio_id' => $municipio->id,
        'dispatch_year' => (int) now()->format('Y'),
        'talao' => 882000 + random_int(1, 999),
        'status' => IncidentStatus::PendingFinalReport,
        'nature_id' => Nature::query()
            ->where('report_modality', IncidentReportModality::FireForest->value)
            ->value('id'),
        'occurred_at' => now(),
        'caller_phone' => '11999992222',
    ]);

    /** @var User $nurse */
    $nurse = User::query()->where('email', 'enfermeiro@example.com')->firstOrFail();

    $this->actingAs($nurse)
        ->get(route('operations.incidents.final-report.document', $incident))
        ->assertForbidden();
});

test('samu nature without report modality still allows nurse report', function (): void {
    $incident = Incident::query()->create([
        'municipio_id' => Municipio::query()->value('id'),
        'dispatch_year' => (int) now()->format('Y'),
        'talao' => 883000 + random_int(1, 999),
        'status' => IncidentStatus::PendingNurseReport,
        'nature_id' => Nature::query()->firstOrFail()->id,
        'occurred_at' => now(),
        'patient_call_type' => 'N',
        'description' => 'Atendimento pré-hospitalar',
        'caller_phone' => '11999993333',
    ]);

    /** @var User $nurse */
    $nurse = User::query()->where('email', 'enfermeiro@example.com')->firstOrFail();

    $this->actingAs($nurse)
        ->get(route('operations.incidents.nurse-report', $incident))
        ->assertOk();
});
