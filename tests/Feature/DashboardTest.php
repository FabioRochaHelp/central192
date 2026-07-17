<?php

declare(strict_types=1);

use App\Domain\Operations\Enums\CallType;
use App\Domain\Operations\Enums\IncidentStatus;
use App\Domain\Operations\Events\DashboardCallStatsInvalidate;
use App\Livewire\Dashboard;
use App\Models\Incident;
use App\Models\Municipio;
use App\Models\Nature;
use App\Models\User;
use Database\Seeders\OperationalDemoSeeder;
use Illuminate\Support\Facades\Event;
use Livewire\Livewire;

test('guests are redirected to the login page', function () {
    $response = $this->get(route('dashboard'));
    $response->assertRedirect(route('login'));
});

test('authenticated users can visit the dashboard', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $response = $this->get(route('dashboard'));
    $response->assertOk();
});

test('operational dashboard shows call type totals for today', function (): void {
    $this->seed(OperationalDemoSeeder::class);

    /** @var Municipio $municipio */
    $municipio = Municipio::query()->firstOrFail();
    /** @var Nature $nature */
    $nature = Nature::query()->firstOrFail();

    $baseTalao = 910_000;
    foreach (CallType::orderedForDashboard() as $i => $type) {
        Incident::query()->create([
            'municipio_id' => $municipio->id,
            'dispatch_year' => (int) now()->format('Y'),
            'talao' => $baseTalao + $i,
            'status' => IncidentStatus::Open,
            'nature_id' => $nature->id,
            'occurred_at' => now(),
            'patient_call_type' => $type->value,
            'description' => 'Dashboard chamadas '.$type->value,
            'caller_phone' => '119888800'.$i,
        ]);
    }

    /** @var User $municipal */
    $municipal = User::query()->where('email', 'municipal@example.com')->firstOrFail();

    $html = Livewire::actingAs($municipal)
        ->test(Dashboard::class)
        ->assertSee(__('Chamadas por tipo'))
        ->html();

    foreach (CallType::orderedForDashboard() as $type) {
        expect($html)->toContain('data-test="call-count-'.$type->value.'"')
            ->and($html)->toMatch('/data-test="call-count-'.$type->value.'"[^>]*>1</');
    }

    Incident::query()->create([
        'municipio_id' => $municipio->id,
        'dispatch_year' => (int) now()->format('Y'),
        'talao' => $baseTalao + 99,
        'status' => IncidentStatus::Open,
        'nature_id' => $nature->id,
        'occurred_at' => now()->subDay(),
        'patient_call_type' => CallType::Normal->value,
        'description' => 'Ontem',
        'caller_phone' => '11977779999',
    ]);

    $htmlAfter = Livewire::actingAs($municipal)->test(Dashboard::class)->html();
    expect($htmlAfter)->toMatch('/data-test="call-count-N"[^>]*>1</');
});

test('dashboard increments refresh tick when Livewire receives dashboard-call-stats-refresh', function (): void {
    $this->seed(OperationalDemoSeeder::class);

    /** @var User $municipal */
    $municipal = User::query()->where('email', 'municipal@example.com')->firstOrFail();

    Livewire::actingAs($municipal)
        ->test(Dashboard::class)
        ->assertSet('callStatsBroadcastTick', 0)
        ->dispatch('dashboard-call-stats-refresh')
        ->assertSet('callStatsBroadcastTick', 1);
});

test('changing incident call type dispatches dashboard stats invalidate broadcast event', function (): void {
    Event::fake([DashboardCallStatsInvalidate::class]);

    $this->seed(OperationalDemoSeeder::class);

    /** @var Municipio $municipio */
    $municipio = Municipio::query()->firstOrFail();
    /** @var Nature $nature */
    $nature = Nature::query()->firstOrFail();

    $incident = Incident::query()->create([
        'municipio_id' => $municipio->id,
        'dispatch_year' => (int) now()->format('Y'),
        'talao' => 920_001,
        'status' => IncidentStatus::Open,
        'nature_id' => $nature->id,
        'occurred_at' => now(),
        'patient_call_type' => CallType::Normal->value,
        'description' => 'Observer broadcast test',
        'caller_phone' => '11900001111',
    ]);

    $incident->update(['patient_call_type' => CallType::Urgent->value]);

    Event::assertDispatched(DashboardCallStatsInvalidate::class);
});
