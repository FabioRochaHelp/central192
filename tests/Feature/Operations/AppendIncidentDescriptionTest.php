<?php

declare(strict_types=1);

use App\Domain\Operations\Actions\AppendIncidentDescriptionAction;
use App\Domain\Operations\Enums\DispatchStage;
use App\Domain\Operations\Enums\IncidentStatus;
use App\Domain\Operations\Enums\ShiftStatus;
use App\Livewire\Operations\DispatchBoard;
use App\Livewire\Operations\IncidentOperationalDetail;
use App\Models\Incident;
use App\Models\IncidentDispatch;
use App\Models\IncidentEvent;
use App\Models\Shift;
use App\Models\User;
use Database\Seeders\OperationalDemoSeeder;
use Livewire\Livewire;

beforeEach(function (): void {
    $this->seed(OperationalDemoSeeder::class);
});

test('append incident description action preserves previous text', function (): void {
    /** @var User $user */
    $user = User::query()->where('email', 'municipal@example.com')->firstOrFail();

    /** @var Incident $incident */
    $incident = Incident::query()->create([
        'municipio_id' => $user->municipio_id,
        'dispatch_year' => (int) now()->format('Y'),
        'talao' => 880001,
        'status' => IncidentStatus::Open,
        'occurred_at' => now()->subMinutes(10),
        'address_line' => 'Rua Teste',
        'city' => 'Demo',
        'patient_call_type' => 'N',
        'description' => 'Paciente com dor torácica.',
    ]);

    app(AppendIncidentDescriptionAction::class)->execute($incident, 'Sinais vitais estáveis.', $user);

    $incident->refresh();

    /** @var IncidentEvent|null $event */
    $event = IncidentEvent::query()
        ->where('incident_id', $incident->id)
        ->where('event_key', 'incident_description_appended')
        ->first();

    expect($incident->description)
        ->toContain('Paciente com dor torácica.')
        ->toContain('Sinais vitais estáveis.')
        ->toContain($user->name)
        ->toMatch('/\[\d{2}\/\d{2}\/\d{4} \d{2}:\d{2}:\d{2}\]/')
        ->and($event)->not->toBeNull()
        ->and($event->actor_id)->toBe($user->id)
        ->and($event->payload)->toMatchArray([
            'text' => 'Sinais vitais estáveis.',
            'actor_name' => $user->name,
        ])
        ->and($event->payload['recorded_at'] ?? null)->not->toBeNull();
});

test('dispatch board appends description without erasing previous text', function (): void {
    /** @var User $user */
    $user = User::query()->where('email', 'municipal@example.com')->firstOrFail();

    /** @var Incident $incident */
    $incident = Incident::query()->create([
        'municipio_id' => $user->municipio_id,
        'dispatch_year' => (int) now()->format('Y'),
        'talao' => 880002,
        'status' => IncidentStatus::Open,
        'occurred_at' => now()->subMinutes(5),
        'address_line' => 'Rua Teste',
        'city' => 'Demo',
        'patient_call_type' => 'N',
        'description' => 'Descrição inicial.',
    ]);

    Livewire::actingAs($user)
        ->test(DispatchBoard::class)
        ->call('openObservationModal', $incident->id)
        ->set('observationText', 'Nova informação do CCO.')
        ->call('saveObservation')
        ->assertSet('showObservationModal', false);

    $incident->refresh();

    expect($incident->description)
        ->toContain('Descrição inicial.')
        ->toContain('Nova informação do CCO.')
        ->toContain($user->name)
        ->toMatch('/\[\d{2}\/\d{2}\/\d{4} \d{2}:\d{2}:\d{2}\]/');
});

test('observation modal stays open when opened from detail modal', function (): void {
    /** @var User $user */
    $user = User::query()->where('email', 'municipal@example.com')->firstOrFail();

    /** @var Incident $incident */
    $incident = Incident::query()->create([
        'municipio_id' => $user->municipio_id,
        'dispatch_year' => (int) now()->format('Y'),
        'talao' => 880004,
        'status' => IncidentStatus::Open,
        'occurred_at' => now()->subMinutes(2),
        'address_line' => 'Rua Teste',
        'city' => 'Demo',
        'patient_call_type' => 'N',
    ]);

    Livewire::actingAs($user)
        ->test(DispatchBoard::class)
        ->call('openDetailModal', $incident->id)
        ->assertSet('showDetailModal', true)
        ->call('openObservationFromDetail')
        ->assertSet('showDetailModal', false)
        ->assertSet('showObservationModal', true)
        ->assertSet('actionIncidentId', $incident->id)
        ->call('closeDetailModal')
        ->assertSet('showObservationModal', true)
        ->assertSet('actionIncidentId', $incident->id);
});

test('observation modal opens from kanban when unit is dispatched', function (): void {
    /** @var User $user */
    $user = User::query()->where('email', 'municipal@example.com')->firstOrFail();

    /** @var Shift $shift */
    $shift = Shift::query()->where('municipio_id', $user->municipio_id)->firstOrFail();
    $shift->update(['status' => ShiftStatus::Empenhado, 'status_legacy' => 2]);

    /** @var Incident $incident */
    $incident = Incident::query()->create([
        'municipio_id' => $user->municipio_id,
        'dispatch_year' => (int) now()->format('Y'),
        'talao' => 880005,
        'status' => IncidentStatus::Dispatched,
        'occurred_at' => now()->subMinutes(8),
        'address_line' => 'Rua Teste',
        'city' => 'Demo',
        'patient_call_type' => 'N',
        'primary_shift_id' => $shift->id,
    ]);

    /** @var IncidentDispatch $dispatch */
    $dispatch = IncidentDispatch::query()->create([
        'municipio_id' => $user->municipio_id,
        'incident_id' => $incident->id,
        'shift_id' => $shift->id,
        'stage' => DispatchStage::Dispatched,
    ]);

    Livewire::actingAs($user)
        ->test(DispatchBoard::class)
        ->call('openKanbanModal', $dispatch->id)
        ->assertSet('showKanbanModal', true)
        ->call('openObservationFromKanban')
        ->assertSet('showKanbanModal', false)
        ->assertSet('showObservationModal', true)
        ->assertSet('actionIncidentId', $incident->id);
});

test('incident detail page appends description without erasing previous text', function (): void {
    /** @var User $user */
    $user = User::query()->where('email', 'municipal@example.com')->firstOrFail();

    /** @var Incident $incident */
    $incident = Incident::query()->create([
        'municipio_id' => $user->municipio_id,
        'dispatch_year' => (int) now()->format('Y'),
        'talao' => 880003,
        'status' => IncidentStatus::Open,
        'occurred_at' => now()->subMinutes(3),
        'address_line' => 'Rua Teste',
        'city' => 'Demo',
        'patient_call_type' => 'N',
        'description' => 'Primeira anotação.',
    ]);

    Livewire::actingAs($user)
        ->test(IncidentOperationalDetail::class, ['incident' => $incident])
        ->set('descriptionAppend', 'Segunda anotação.')
        ->call('appendDescription')
        ->assertHasNoErrors();

    $incident->refresh();

    expect($incident->description)
        ->toContain('Primeira anotação.')
        ->toContain('Segunda anotação.')
        ->toContain($user->name)
        ->toMatch('/\[\d{2}\/\d{2}\/\d{4} \d{2}:\d{2}:\d{2}\]/');
});
