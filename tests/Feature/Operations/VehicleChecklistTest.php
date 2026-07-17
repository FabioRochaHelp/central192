<?php

declare(strict_types=1);

use App\Domain\Operations\Actions\SaveShiftChecklistAction;
use App\Livewire\Operations\Cadastro\ShiftManage;
use App\Livewire\Operations\Cadastro\VehicleChecklistResourceManage;
use App\Livewire\Operations\DispatchBoard;
use App\Models\Shift;
use App\Models\User;
use App\Models\VehicleChecklistResource;
use Database\Seeders\OperationalDemoSeeder;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

beforeEach(function (): void {
    $this->seed(OperationalDemoSeeder::class);
});

test('central operator can manage checklist resources', function (): void {
    /** @var User $user */
    $user = User::query()->where('email', 'central@example.com')->firstOrFail();

    $municipioId = (int) User::query()->where('email', 'municipal@example.com')->value('municipio_id');

    $this->actingAs($user)
        ->get(route('operations.parameters.checklist-resources'))
        ->assertOk();

    Livewire::actingAs($user)
        ->test(VehicleChecklistResourceManage::class)
        ->set('selectedOperationalMunicipioId', (string) $municipioId)
        ->set('formName', 'Oxigênio')
        ->set('formUnitOfMeasure', 'litros')
        ->call('save')
        ->assertHasNoErrors();

    expect(VehicleChecklistResource::query()->where('name', 'Oxigênio')->exists())->toBeTrue();
});

test('shift checklist can be completed with selected resources only', function (): void {
    /** @var User $user */
    $user = User::query()->where('email', 'municipal@example.com')->firstOrFail();

    $resources = VehicleChecklistResource::query()
        ->where('municipio_id', $user->municipio_id)
        ->orderBy('name')
        ->get();

    expect($resources)->not->toBeEmpty();

    $selectedResource = $resources->first();

    /** @var Shift $shift */
    $shift = Shift::query()->where('municipio_id', $user->municipio_id)->firstOrFail();

    Livewire::actingAs($user)
        ->test(ShiftManage::class)
        ->call('openChecklistModal', $shift->id)
        ->set('checklistSelectedResourceIds', [$selectedResource->id])
        ->set('checklistQuantities', [(string) $selectedResource->id => '2'])
        ->call('saveChecklist', true)
        ->assertHasNoErrors();

    $shift->refresh();

    expect($shift->isChecklistComplete())->toBeTrue()
        ->and($shift->checklistItems)->toHaveCount(1)
        ->and($shift->checklistItems->first()->quantity)->toBe(2);
});

test('shift checklist can be completed without any selected resource', function (): void {
    /** @var User $user */
    $user = User::query()->where('email', 'municipal@example.com')->firstOrFail();

    /** @var Shift $shift */
    $shift = Shift::query()->where('municipio_id', $user->municipio_id)->firstOrFail();

    Livewire::actingAs($user)
        ->test(ShiftManage::class)
        ->call('openChecklistModal', $shift->id)
        ->set('checklistSelectedResourceIds', [])
        ->call('saveChecklist', true)
        ->assertHasNoErrors();

    $shift->refresh();

    expect($shift->isChecklistComplete())->toBeTrue()
        ->and($shift->checklistItems)->toHaveCount(0);
});

test('pending checklist requires observation', function (): void {
    /** @var User $user */
    $user = User::query()->where('email', 'municipal@example.com')->firstOrFail();

    /** @var Shift $shift */
    $shift = Shift::query()->where('municipio_id', $user->municipio_id)->firstOrFail();

    Livewire::actingAs($user)
        ->test(ShiftManage::class)
        ->call('openChecklistModal', $shift->id)
        ->set('checklistSelectedResourceIds', [])
        ->call('saveChecklist', false)
        ->assertHasErrors(['checklistObservation']);

    Livewire::actingAs($user)
        ->test(ShiftManage::class)
        ->call('openChecklistModal', $shift->id)
        ->set('checklistObservation', 'Aguardando conferência do supervisor.')
        ->call('saveChecklist', false)
        ->assertHasNoErrors();

    $shift->refresh();

    expect($shift->isChecklistComplete())->toBeFalse()
        ->and($shift->checklist_observation)->toBe('Aguardando conferência do supervisor.');
});

test('save shift checklist action requires quantity for selected resources', function (): void {
    /** @var Shift $shift */
    $shift = Shift::query()->firstOrFail();

    $resource = VehicleChecklistResource::query()
        ->where('municipio_id', $shift->municipio_id)
        ->firstOrFail();

    expect(fn () => app(SaveShiftChecklistAction::class)->execute(
        $shift,
        [$resource->id],
        [],
        true,
    ))->toThrow(ValidationException::class);
});

test('dispatch board opens shift checklist modal', function (): void {
    /** @var User $user */
    $user = User::query()->where('email', 'municipal@example.com')->firstOrFail();

    /** @var Shift $shift */
    $shift = Shift::query()->where('municipio_id', $user->municipio_id)->firstOrFail();

    Livewire::actingAs($user)
        ->test(DispatchBoard::class)
        ->call('openShiftChecklistModal', $shift->id)
        ->assertSet('showShiftChecklistModal', true)
        ->assertSet('shiftChecklistShiftId', $shift->id);
});
