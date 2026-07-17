<?php

declare(strict_types=1);

use App\Domain\Operations\Actions\AssumeRegulationAction;
use App\Domain\Operations\Actions\RegisterRegulationDecisionAction;
use App\Domain\Operations\DTOs\RegulationDecisionDTO;
use App\Domain\Operations\Enums\IncidentStatus;
use App\Domain\Operations\Enums\ManchesterRisk;
use App\Domain\Operations\Enums\RegulationDecision;
use App\Domain\Operations\Enums\RegulationResource;
use App\Livewire\Operations\DispatchBoard;
use App\Livewire\Operations\Regulation\RegulationForm;
use App\Livewire\Operations\Regulation\RegulationQueue;
use App\Models\Incident;
use App\Models\User;
use Database\Seeders\OperationalDemoSeeder;
use Livewire\Livewire;

beforeEach(function (): void {
    $this->seed(OperationalDemoSeeder::class);
});

function regulationIncident(array $overrides = []): Incident
{
    return Incident::query()->create(array_merge([
        'municipio_id' => null,
        'dispatch_year' => (int) now()->format('Y'),
        'talao' => random_int(900000, 999999),
        'status' => IncidentStatus::PendingRegulation,
        'occurred_at' => now()->subMinutes(6),
        'call_received_at' => now()->subMinutes(6),
        'address_line' => 'Rua Regulação',
        'city' => 'Demo',
        'patient_call_type' => 'N',
        'description' => 'Dor torácica há 30 minutos.',
    ], $overrides));
}

function medico(): User
{
    return User::query()->where('email', 'medico@example.com')->firstOrFail();
}

test('médico assume ocorrência e ela vai para em regulação', function (): void {
    $incident = regulationIncident();

    $regulation = app(AssumeRegulationAction::class)->execute($incident->id, medico());

    $incident->refresh();

    expect($incident->status)->toBe(IncidentStatus::InRegulation)
        ->and($regulation->regulator_user_id)->toBe(medico()->id)
        ->and($regulation->assumed_at)->not->toBeNull();
});

test('segundo médico não assume ocorrência já em regulação', function (): void {
    $incident = regulationIncident();

    app(AssumeRegulationAction::class)->execute($incident->id, medico());

    /** @var User $outroMedico */
    $outroMedico = User::factory()->create([
        'municipio_id' => null,
        'users_type_legacy' => 4,
    ]);

    app(AssumeRegulationAction::class)->execute($incident->id, $outroMedico);
})->throws(RuntimeException::class);

test('decisão de envio abre a ocorrência para despacho com recurso e tempo-resposta', function (): void {
    $incident = regulationIncident();
    app(AssumeRegulationAction::class)->execute($incident->id, medico());

    $regulation = app(RegisterRegulationDecisionAction::class)->execute(new RegulationDecisionDTO(
        incidentId: $incident->id,
        regulatorUserId: medico()->id,
        decision: RegulationDecision::DispatchResource,
        priority: ManchesterRisk::Orange,
        diagnosticHypothesis: 'Suspeita de SCA',
        recommendedResource: RegulationResource::Usa,
    ));

    $incident->refresh();

    expect($incident->status)->toBe(IncidentStatus::Open)
        ->and($incident->manchester_risk)->toBe(ManchesterRisk::Orange)
        ->and($regulation->status)->toBe(RegulationDecision::DispatchResource)
        ->and($regulation->recommended_resource)->toBe(RegulationResource::Usa)
        ->and($regulation->response_time_seconds)->toBeGreaterThan(0)
        ->and($regulation->decided_at)->not->toBeNull();
});

test('orientação médica encerra sem viatura e sem recurso', function (): void {
    $incident = regulationIncident();
    app(AssumeRegulationAction::class)->execute($incident->id, medico());

    $regulation = app(RegisterRegulationDecisionAction::class)->execute(new RegulationDecisionDTO(
        incidentId: $incident->id,
        regulatorUserId: medico()->id,
        decision: RegulationDecision::MedicalGuidance,
        guidanceNotes: 'Orientado repouso e retorno se piora.',
    ));

    $incident->refresh();

    expect($incident->status)->toBe(IncidentStatus::RegulationDenied)
        ->and($regulation->recommended_resource)->toBeNull()
        ->and($regulation->guidance_notes)->toContain('repouso');
});

test('ocorrência em regulação não aparece na fila de despacho', function (): void {
    $incident = regulationIncident();

    /** @var User $despachador */
    $despachador = User::query()->where('email', 'despachador@example.com')->firstOrFail();

    Livewire::actingAs($despachador)
        ->test(DispatchBoard::class)
        ->assertDontSee((string) $incident->talao);
});

test('após decisão de envio a ocorrência aparece na fila de despacho', function (): void {
    $incident = regulationIncident();
    app(AssumeRegulationAction::class)->execute($incident->id, medico());
    app(RegisterRegulationDecisionAction::class)->execute(new RegulationDecisionDTO(
        incidentId: $incident->id,
        regulatorUserId: medico()->id,
        decision: RegulationDecision::DispatchResource,
        recommendedResource: RegulationResource::Usb,
    ));

    /** @var User $despachador */
    $despachador = User::query()->where('email', 'despachador@example.com')->firstOrFail();

    Livewire::actingAs($despachador)
        ->test(DispatchBoard::class)
        ->assertSee((string) $incident->talao);
});

test('fila de regulação assume e redireciona para o formulário', function (): void {
    $incident = regulationIncident();

    Livewire::actingAs(medico())
        ->test(RegulationQueue::class)
        ->assertSee((string) $incident->talao)
        ->call('assume', $incident->id)
        ->assertRedirect(route('operations.incidents.regulation', $incident));

    expect($incident->fresh()->status)->toBe(IncidentStatus::InRegulation);
});

test('formulário registra decisão e roteia para o detalhe', function (): void {
    $incident = regulationIncident();

    Livewire::actingAs(medico())
        ->test(RegulationForm::class, ['incident' => $incident])
        ->set('priority', ManchesterRisk::Red->value)
        ->set('decision', RegulationDecision::DispatchResource->value)
        ->set('recommended_resource', RegulationResource::Usa->value)
        ->call('save')
        ->assertHasNoErrors()
        ->assertRedirect(route('operations.incidents.show', $incident));

    expect($incident->fresh()->status)->toBe(IncidentStatus::Open);
});

test('formulário exige recurso quando a decisão é enviar', function (): void {
    $incident = regulationIncident();

    Livewire::actingAs(medico())
        ->test(RegulationForm::class, ['incident' => $incident])
        ->set('decision', RegulationDecision::DispatchResource->value)
        ->set('recommended_resource', '')
        ->call('save')
        ->assertHasErrors('recommended_resource');
});

test('despachador não pode regular', function (): void {
    $incident = regulationIncident();

    /** @var User $despachador */
    $despachador = User::query()->where('email', 'despachador@example.com')->firstOrFail();

    expect($despachador->can('regulate', $incident))->toBeFalse()
        ->and(medico()->can('regulate', $incident))->toBeTrue();
});
