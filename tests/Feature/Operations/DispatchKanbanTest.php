<?php

declare(strict_types=1);

use App\Domain\Operations\Actions\CancelDispatchAtSceneAction;
use App\Domain\Operations\Enums\DispatchSceneCancelReason;
use App\Domain\Operations\Enums\DispatchStage;
use App\Domain\Operations\Enums\IncidentStatus;
use App\Domain\Operations\Enums\ShiftStatus;
use App\Livewire\Operations\DispatchBoard;
use App\Models\Incident;
use App\Models\IncidentDispatch;
use App\Models\Shift;
use App\Models\User;
use Database\Seeders\OperationalDemoSeeder;
use Livewire\Livewire;

beforeEach(function (): void {
    $this->seed(OperationalDemoSeeder::class);
});

test('cancel dispatch at scene moves kanban to left scene and marks incident as qta', function (): void {
    /** @var User $user */
    $user = User::query()->where('email', 'municipal@example.com')->firstOrFail();

    /** @var Shift $shift */
    $shift = Shift::query()->where('municipio_id', $user->municipio_id)->firstOrFail();
    $shift->update(['status' => ShiftStatus::Empenhado, 'status_legacy' => 2]);

    /** @var Incident $incident */
    $incident = Incident::query()->create([
        'municipio_id' => $user->municipio_id,
        'dispatch_year' => (int) now()->format('Y'),
        'talao' => 770001,
        'status' => IncidentStatus::Dispatched,
        'occurred_at' => now()->subMinutes(20),
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
        'stage' => DispatchStage::ArrivedScene,
    ]);

    app(CancelDispatchAtSceneAction::class)->execute(
        $dispatch,
        DispatchSceneCancelReason::NothingFound,
        $user->id,
    );

    $dispatch->refresh();
    $incident->refresh();

    expect($dispatch->stage)->toBe(DispatchStage::LeftScene)
        ->and($dispatch->left_scene_at)->not->toBeNull()
        ->and($dispatch->cancelled_at_scene_at)->not->toBeNull()
        ->and($incident->status)->toBe(IncidentStatus::Qta)
        ->and($incident->is_qta)->toBeTrue();
});

test('dispatch board kanban modal cancel updates stage to left scene', function (): void {
    /** @var User $user */
    $user = User::query()->where('email', 'municipal@example.com')->firstOrFail();

    /** @var Shift $shift */
    $shift = Shift::query()->where('municipio_id', $user->municipio_id)->firstOrFail();
    $shift->update(['status' => ShiftStatus::Empenhado, 'status_legacy' => 2]);

    /** @var Incident $incident */
    $incident = Incident::query()->create([
        'municipio_id' => $user->municipio_id,
        'dispatch_year' => (int) now()->format('Y'),
        'talao' => 770002,
        'status' => IncidentStatus::Dispatched,
        'occurred_at' => now()->subMinutes(15),
        'address_line' => 'Av. Central',
        'city' => 'Demo',
        'patient_call_type' => 'U',
        'primary_shift_id' => $shift->id,
    ]);

    /** @var IncidentDispatch $dispatch */
    $dispatch = IncidentDispatch::query()->create([
        'municipio_id' => $user->municipio_id,
        'incident_id' => $incident->id,
        'shift_id' => $shift->id,
        'stage' => DispatchStage::ArrivedScene,
    ]);

    Livewire::actingAs($user)
        ->test(DispatchBoard::class)
        ->call('openKanbanModal', $dispatch->id)
        ->assertSet('showKanbanModal', true)
        ->call('cancelDispatchAtScene', DispatchSceneCancelReason::CcoOrder->value)
        ->assertSet('showKanbanModal', false);

    $dispatch->refresh();
    $incident->refresh();

    expect($dispatch->stage)->toBe(DispatchStage::LeftScene)
        ->and($incident->status)->toBe(IncidentStatus::Qta);
});

test('move kanban dispatch rejects skipping stages', function (): void {
    /** @var User $user */
    $user = User::query()->where('email', 'municipal@example.com')->firstOrFail();

    /** @var Shift $shift */
    $shift = Shift::query()->where('municipio_id', $user->municipio_id)->firstOrFail();

    /** @var Incident $incident */
    $incident = Incident::query()->create([
        'municipio_id' => $user->municipio_id,
        'dispatch_year' => (int) now()->format('Y'),
        'talao' => 770003,
        'status' => IncidentStatus::Dispatched,
        'occurred_at' => now(),
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
        ->call('moveKanbanDispatch', $dispatch->id, 0, DispatchStage::ArrivedScene->value)
        ->assertHasErrors('board');

    expect($dispatch->fresh()->stage)->toBe(DispatchStage::Dispatched);
});
