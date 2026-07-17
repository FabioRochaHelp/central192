<?php

declare(strict_types=1);

use App\Livewire\Operations\CallAlertIndex;
use App\Models\OperationalCallAlert;
use App\Models\User;
use App\Support\Operations\OperationalCallAlertGrouper;
use Database\Seeders\OperationalDemoSeeder;
use Livewire\Livewire;

beforeEach(function (): void {
    $this->seed(OperationalDemoSeeder::class);
});

test('central user can access call alerts page and see pending alert', function (): void {
    $reference = 'PBX-TEST-9001';

    OperationalCallAlert::factory()->create([
        'latitude' => -23.55012,
        'longitude' => -46.63045,
        'external_reference' => $reference,
        'caller_name' => 'Maria Teste',
    ]);

    /** @var User $central */
    $central = User::query()->where('email', 'central@example.com')->firstOrFail();

    $this->actingAs($central)
        ->get(route('operations.call-alerts.index'))
        ->assertOk()
        ->assertSee('Alertas pendentes')
        ->assertSee($reference)
        ->assertSee('Maria Teste');
});

test('call alert index lists active alerts via livewire', function (): void {
    OperationalCallAlert::factory()->count(2)->create([
        'latitude' => -23.55012,
        'longitude' => -46.63045,
    ]);

    /** @var User $central */
    $central = User::query()->where('email', 'central@example.com')->firstOrFail();

    Livewire::actingAs($central)
        ->test(CallAlertIndex::class)
        ->assertSee('2×');
});


test('call alert index refreshes from reverb livewire event', function (): void {
    OperationalCallAlert::factory()->create([
        'external_reference' => 'PBX-FIRST',
    ]);

    /** @var User $central */
    $central = User::query()->where('email', 'central@example.com')->firstOrFail();

    $component = Livewire::actingAs($central)->test(CallAlertIndex::class);
    $component->assertSee('PBX-FIRST');

    OperationalCallAlert::factory()->create([
        'external_reference' => 'PBX-REVERB-NEW',
    ]);

    $component
        ->dispatch('call-alert-index-refresh')
        ->assertSee('PBX-REVERB-NEW');
});
test('abort alert removes cluster from list', function (): void {
    OperationalCallAlert::factory()->create([
        'latitude' => -23.55012,
        'longitude' => -46.63045,
        'external_reference' => 'PBX-ABORT-1',
    ]);

    $locationKey = OperationalCallAlertGrouper::locationKey(-23.55012, -46.63045);

    /** @var User $central */
    $central = User::query()->where('email', 'central@example.com')->firstOrFail();

    Livewire::actingAs($central)
        ->test(CallAlertIndex::class)
        ->assertSee('PBX-ABORT-1')
        ->call('abortAlert', $locationKey)
        ->assertDontSee('PBX-ABORT-1')
        ->assertSee(__('Nenhum alerta pendente'));
});
