<?php

declare(strict_types=1);

use App\Domain\Operations\Enums\ShiftStatus;
use App\Livewire\Operations\Cadastro\ShiftManage;
use App\Livewire\Operations\DispatchBoard;
use App\Models\Shift;
use App\Models\User;
use App\Models\Vehicle;
use Database\Seeders\OperationalDemoSeeder;
use Livewire\Livewire;

beforeEach(function (): void {
    $this->seed(OperationalDemoSeeder::class);
});

test('operational idle shift can be opened without staff', function (): void {
    /** @var User $user */
    $user = User::query()->where('email', 'municipal@example.com')->firstOrFail();

    /** @var Vehicle $vehicle */
    $vehicle = Vehicle::query()->where('municipio_id', $user->municipio_id)->firstOrFail();

    Shift::query()->where('vehicle_id', $vehicle->id)->update(['ends_at' => now()->subMinute()]);

    Livewire::actingAs($user)
        ->test(ShiftManage::class)
        ->set('vehicle_id', (string) $vehicle->id)
        ->set('ends_at', now()->addHours(8)->format('Y-m-d\TH:i'))
        ->set('status', ShiftStatus::Oficina->value)
        ->set('staffIds', [])
        ->call('save')
        ->assertHasNoErrors();

    $shift = Shift::query()
        ->where('vehicle_id', $vehicle->id)
        ->where('status', ShiftStatus::Oficina)
        ->latest('id')
        ->first();

    expect($shift)->not->toBeNull()
        ->and($shift->staff)->toHaveCount(0)
        ->and($shift->checklist_completed_at)->toBeNull();
});

test('dispatch board lists operational idle shifts beside vehicles without shift', function (): void {
    /** @var User $user */
    $user = User::query()->where('email', 'municipal@example.com')->firstOrFail();

    /** @var Vehicle $vehicle */
    $vehicle = Vehicle::query()->where('municipio_id', $user->municipio_id)->firstOrFail();

    Shift::query()->where('vehicle_id', $vehicle->id)->update(['ends_at' => now()->subMinute()]);

    Shift::query()->create([
        'municipio_id' => $user->municipio_id,
        'vehicle_id' => $vehicle->id,
        'starts_at' => now()->subHour(),
        'ends_at' => now()->addHours(8),
        'status' => ShiftStatus::Acidente,
        'status_legacy' => null,
        'available_at' => null,
    ]);

    Livewire::actingAs($user)
        ->test(DispatchBoard::class)
        ->assertSee($vehicle->prefix)
        ->assertSee(__('Acidente'));
});
