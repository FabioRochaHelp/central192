<?php

declare(strict_types=1);

use App\Domain\Operations\Enums\IncidentStatus;
use App\Livewire\Operations\IncidentNurseReport;
use App\Models\Incident;
use App\Models\IncidentNurseReport as IncidentNurseReportModel;
use App\Models\Municipio;
use App\Models\Nature;
use App\Models\User;
use Database\Seeders\OperationalDemoSeeder;
use Livewire\Livewire;

beforeEach(function (): void {
    $this->seed(OperationalDemoSeeder::class);
});

/** Ocorrência após liberação da viatura — aguarda relatório para encerrar. */
function createPendingNurseReportIncidentForTests(): Incident
{
    /** @var Municipio $municipio */
    $municipio = Municipio::query()->firstOrFail();
    $nature = Nature::query()->firstOrFail();

    return Incident::query()->create([
        'municipio_id' => $municipio->id,
        'dispatch_year' => (int) now()->format('Y'),
        'talao' => 770000 + random_int(1, 999),
        'status' => IncidentStatus::PendingNurseReport,
        'nature_id' => $nature->id,
        'occurred_at' => now(),
        'patient_call_type' => 'N',
        'description' => 'Teste relatório enfermagem',
        'caller_phone' => '11999998888',
    ]);
}

test('municipal operator without nurse profile cannot open nurse report screen', function (): void {
    $incident = createPendingNurseReportIncidentForTests();

    /** @var User $municipal */
    $municipal = User::query()->where('email', 'municipal@example.com')->firstOrFail();

    $this->actingAs($municipal)
        ->get(route('operations.incidents.nurse-report', $incident))
        ->assertForbidden();
});

test('nurse cannot fill report while incident is still in operational cycle', function (): void {
    /** @var Municipio $municipio */
    $municipio = Municipio::query()->firstOrFail();
    $nature = Nature::query()->firstOrFail();

    $open = Incident::query()->create([
        'municipio_id' => $municipio->id,
        'dispatch_year' => (int) now()->format('Y'),
        'talao' => 780000 + random_int(1, 999),
        'status' => IncidentStatus::Open,
        'nature_id' => $nature->id,
        'occurred_at' => now(),
        'patient_call_type' => 'N',
        'description' => 'Aberta',
        'caller_phone' => '11999997777',
    ]);

    /** @var User $nurse */
    $nurse = User::query()->where('email', 'enfermeiro@example.com')->firstOrFail();

    $this->actingAs($nurse)
        ->get(route('operations.incidents.nurse-report', $open))
        ->assertForbidden();
});

test('nurse can save report on pending incident and closure is recorded', function (): void {
    $incident = createPendingNurseReportIncidentForTests();

    /** @var User $nurse */
    $nurse = User::query()->where('email', 'enfermeiro@example.com')->firstOrFail();

    Livewire::actingAs($nurse)
        ->test(IncidentNurseReport::class, ['incident' => $incident])
        ->set('clinical_evolution', 'Paciente estável durante transporte.')
        ->set('conduct_summary', 'Monitorização contínua.')
        ->set('destination_notes', 'US receptora setor adulto.')
        ->call('save')
        ->assertHasNoErrors()
        ->assertRedirect(route('operations.incidents.show', $incident));

    $report = IncidentNurseReportModel::query()->where('incident_id', $incident->id)->first();
    expect($report)->not->toBeNull()
        ->and($report->user_id)->toBe($nurse->id)
        ->and($report->clinical_evolution)->toContain('estável');

    $fresh = $incident->fresh();
    expect($fresh->status)->toBe(IncidentStatus::Closed)
        ->and($fresh->incidentEvents()->where('event_key', 'nurse_report_saved')->exists())->toBeTrue()
        ->and($fresh->incidentEvents()->where('event_key', 'incident_closed')->exists())->toBeTrue();
});

test('nurse can save report on legacy closed incident without extra incident_closed event', function (): void {
    /** @var Municipio $municipio */
    $municipio = Municipio::query()->firstOrFail();
    $nature = Nature::query()->firstOrFail();

    $incident = Incident::query()->create([
        'municipio_id' => $municipio->id,
        'dispatch_year' => (int) now()->format('Y'),
        'talao' => 765000 + random_int(1, 999),
        'status' => IncidentStatus::Closed,
        'nature_id' => $nature->id,
        'occurred_at' => now(),
        'patient_call_type' => 'N',
        'description' => 'Legado já encerrada',
        'caller_phone' => '11999995555',
    ]);

    /** @var User $nurse */
    $nurse = User::query()->where('email', 'enfermeiro@example.com')->firstOrFail();

    Livewire::actingAs($nurse)
        ->test(IncidentNurseReport::class, ['incident' => $incident])
        ->set('clinical_evolution', 'Relatório tardio em ocorrência já encerrada.')
        ->call('save')
        ->assertHasNoErrors();

    expect($incident->fresh()->status)->toBe(IncidentStatus::Closed)
        ->and($incident->fresh()->incidentEvents()->where('event_key', 'incident_closed')->count())->toBe(0);
});
