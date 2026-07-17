<?php

declare(strict_types=1);

use App\Domain\Operations\Actions\AssumeRegulationAction;
use App\Domain\Operations\Actions\RegisterRegulationDecisionAction;
use App\Domain\Operations\DTOs\RegulationDecisionDTO;
use App\Domain\Operations\Enums\IncidentStatus;
use App\Domain\Operations\Enums\ManchesterRisk;
use App\Domain\Operations\Enums\RegulationDecision;
use App\Domain\Operations\Enums\RegulationResource;
use App\Livewire\Operations\Reports\RegulationReport;
use App\Models\Incident;
use App\Models\User;
use App\Support\Operations\Reports\RegulationReportQuery;
use Database\Seeders\OperationalDemoSeeder;
use Livewire\Livewire;

beforeEach(function (): void {
    $this->seed(OperationalDemoSeeder::class);
});

function decidedIncident(RegulationDecision $decision, array $regOverrides = []): Incident
{
    /** @var User $medico */
    $medico = User::query()->where('email', 'medico@example.com')->firstOrFail();

    $incident = Incident::query()->create([
        'municipio_id' => $medico->municipio_id,
        'dispatch_year' => (int) now()->format('Y'),
        'talao' => random_int(900000, 999999),
        'status' => IncidentStatus::PendingRegulation,
        'occurred_at' => now()->subMinutes(6),
        'call_received_at' => now()->subMinutes(6),
        'address_line' => 'Rua Relatório',
        'city' => 'Demo',
        'patient_call_type' => 'N',
        'description' => 'Teste relatório',
    ]);

    app(AssumeRegulationAction::class)->execute($incident->id, $medico);
    app(RegisterRegulationDecisionAction::class)->execute(new RegulationDecisionDTO(
        incidentId: $incident->id,
        regulatorUserId: $medico->id,
        decision: $decision,
        priority: $regOverrides['priority'] ?? ManchesterRisk::Yellow,
        recommendedResource: $regOverrides['resource'] ?? RegulationResource::Usb,
    ));

    return $incident->fresh();
}

test('agregação de regulação computa total, decisão e percentuais', function (): void {
    decidedIncident(RegulationDecision::DispatchResource);
    decidedIncident(RegulationDecision::DispatchResource);
    decidedIncident(RegulationDecision::MedicalGuidance);

    /** @var User $medico */
    $medico = User::query()->where('email', 'medico@example.com')->firstOrFail();

    $data = app(RegulationReportQuery::class)->build($medico, [
        'from' => now()->subDay()->toDateString(),
        'to' => now()->toDateString(),
    ]);

    expect($data['total'])->toBe(3)
        ->and($data['dispatch_pct'])->toBe(66.7)
        ->and($data['guidance_pct'])->toBe(33.3)
        ->and($data['response_time']['sample'])->toBe(3)
        ->and(collect($data['by_decision'])->firstWhere('key', RegulationDecision::DispatchResource->value)['count'])->toBe(2);
});

test('tela de relatório de regulação abre para o médico', function (): void {
    decidedIncident(RegulationDecision::DispatchResource);

    /** @var User $medico */
    $medico = User::query()->where('email', 'medico@example.com')->firstOrFail();

    Livewire::actingAs($medico)
        ->test(RegulationReport::class)
        ->assertOk()
        ->assertSee(__('Relatório de regulação médica'));
});

test('relatório de regulação é negado ao despachador', function (): void {
    /** @var User $despachador */
    $despachador = User::query()->where('email', 'despachador@example.com')->firstOrFail();

    $this->actingAs($despachador)
        ->get(route('operations.reports.regulation.index'))
        ->assertForbidden();
});

test('ficha de regulação em PDF é gerada para ocorrência regulada', function (): void {
    $incident = decidedIncident(RegulationDecision::DispatchResource);

    /** @var User $medico */
    $medico = User::query()->where('email', 'medico@example.com')->firstOrFail();

    $response = $this->actingAs($medico)
        ->get(route('operations.incidents.regulation.document', $incident));

    $response->assertOk();
    expect($response->headers->get('content-type'))->toContain('application/pdf');
});

test('ficha de regulação retorna 404 quando ainda não decidida', function (): void {
    /** @var User $medico */
    $medico = User::query()->where('email', 'medico@example.com')->firstOrFail();

    $incident = Incident::query()->create([
        'municipio_id' => $medico->municipio_id,
        'dispatch_year' => (int) now()->format('Y'),
        'talao' => 950001,
        'status' => IncidentStatus::PendingRegulation,
        'occurred_at' => now(),
        'call_received_at' => now(),
        'address_line' => 'Rua X',
        'city' => 'Demo',
        'patient_call_type' => 'N',
    ]);

    $this->actingAs($medico)
        ->get(route('operations.incidents.regulation.document', $incident))
        ->assertNotFound();
});
