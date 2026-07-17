<?php

declare(strict_types=1);

use App\Livewire\Operations\CallAlertFireReportModal;
use App\Models\OperationalCallAlert;
use App\Models\User;
use App\Support\Operations\OperationalCallAlertGrouper;
use Database\Seeders\OperationalDemoSeeder;
use Livewire\Livewire;

beforeEach(function (): void {
    $this->seed(OperationalDemoSeeder::class);
});

test('call alert fire report modal opens when event is dispatched with active alerts', function (): void {
    OperationalCallAlert::factory()->create([
        'latitude' => -23.55012,
        'longitude' => -46.63045,
        'metadata' => [
            'temperature' => 305.0,
            'humidity' => 35.0,
            'wind_speed' => 15.0,
        ],
    ]);

    $locationKey = OperationalCallAlertGrouper::locationKey(-23.55012, -46.63045);

    /** @var User $central */
    $central = User::query()->where('email', 'central@example.com')->firstOrFail();

    Livewire::actingAs($central)
        ->test(CallAlertFireReportModal::class)
        ->assertSet('showModal', false)
        ->dispatch('call-alert-fire-report', locationKey: $locationKey)
        ->assertSet('showModal', true)
        ->assertSet('report.location_key', $locationKey)
        ->assertSet('report.alert_count', 1);
});

test('call alert fire report modal closes and clears report', function (): void {
    OperationalCallAlert::factory()->create([
        'latitude' => -23.55012,
        'longitude' => -46.63045,
        'metadata' => ['temperature' => 300.0],
    ]);

    $locationKey = OperationalCallAlertGrouper::locationKey(-23.55012, -46.63045);

    /** @var User $central */
    $central = User::query()->where('email', 'central@example.com')->firstOrFail();

    Livewire::actingAs($central)
        ->test(CallAlertFireReportModal::class)
        ->dispatch('call-alert-fire-report', locationKey: $locationKey)
        ->assertSet('showModal', true)
        ->call('close')
        ->assertSet('showModal', false)
        ->assertSet('report', []);
});
