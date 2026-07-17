<?php

declare(strict_types=1);

use App\Domain\Operations\Enums\DispatchStage;
use App\Domain\Operations\Enums\IncidentStatus;
use App\Domain\Operations\Enums\ShiftStatus;
use App\Livewire\Operations\IncidentOperationalDetail;
use App\Models\Incident;
use App\Models\IncidentDispatch;
use App\Models\Shift;
use App\Models\User;
use App\Models\Vehicle;
use Database\Seeders\OperationalDemoSeeder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;

beforeEach(function (): void {
    $this->seed(OperationalDemoSeeder::class);
    Cache::flush();

    config([
        'traccar.base_url' => 'https://traccar.test',
        'traccar.auth_type' => 'basic',
        'traccar.username' => 'reader',
        'traccar.password' => 'secret',
        'traccar.timeout' => 5,
        'traccar.verify_ssl' => true,
    ]);
});

test('incident route endpoint returns traccar points for dispatched incident', function (): void {
    /** @var User $user */
    $user = User::query()->where('email', 'municipal@example.com')->firstOrFail();

    /** @var Vehicle $vehicle */
    $vehicle = Vehicle::query()->firstOrFail();
    $vehicle->update(['device_id' => '1001']);

    /** @var Shift $shift */
    $shift = Shift::query()->where('vehicle_id', $vehicle->id)->firstOrFail();
    $shift->update(['status' => ShiftStatus::Empenhado]);

    $dispatchedAt = now()->subMinutes(30);

    /** @var Incident $incident */
    $incident = Incident::query()->create([
        'municipio_id' => $user->municipio_id,
        'dispatch_year' => (int) now()->format('Y'),
        'talao' => 880100,
        'status' => IncidentStatus::Dispatched,
        'occurred_at' => now()->subMinutes(45),
        'dispatched_at' => $dispatchedAt,
        'address_line' => 'Rua Teste',
        'city' => 'Demo',
        'patient_call_type' => 'N',
        'primary_shift_id' => $shift->id,
        'latitude' => -15.79,
        'longitude' => -47.88,
    ]);

    IncidentDispatch::query()->create([
        'municipio_id' => $user->municipio_id,
        'incident_id' => $incident->id,
        'shift_id' => $shift->id,
        'stage' => DispatchStage::Dispatched,
        'dispatched_at' => $dispatchedAt,
    ]);

    Http::fake([
        'https://traccar.test/api/reports/route*' => Http::response([
            [
                'latitude' => -15.791,
                'longitude' => -47.881,
                'fixTime' => '2026-06-10T10:00:00.000Z',
                'speed' => 5,
            ],
            [
                'latitude' => -15.792,
                'longitude' => -47.882,
                'fixTime' => '2026-06-10T10:05:00.000Z',
                'speed' => 8,
            ],
        ], 200),
    ]);

    $response = $this->actingAs($user)
        ->getJson(route('operations.incidents.route', $incident));

    $response->assertOk()
        ->assertJsonPath('count', 2)
        ->assertJsonPath('points.0.lat', -15.791)
        ->assertJsonPath('points.1.lng', -47.882);
});

test('incident route endpoint returns validation error without dispatch time', function (): void {
    /** @var User $user */
    $user = User::query()->where('email', 'municipal@example.com')->firstOrFail();

    /** @var Incident $incident */
    $incident = Incident::query()->create([
        'municipio_id' => $user->municipio_id,
        'dispatch_year' => (int) now()->format('Y'),
        'talao' => 880101,
        'status' => IncidentStatus::Open,
        'occurred_at' => now()->subMinutes(5),
        'address_line' => 'Rua Teste',
        'city' => 'Demo',
        'patient_call_type' => 'N',
    ]);

    $this->actingAs($user)
        ->getJson(route('operations.incidents.route', $incident))
        ->assertUnprocessable()
        ->assertJsonPath('error', __('Ocorrência sem hora de empenho registrada.'));
});

test('incident detail page shows vehicle route map section', function (): void {
    /** @var User $user */
    $user = User::query()->where('email', 'municipal@example.com')->firstOrFail();

    /** @var Vehicle $vehicle */
    $vehicle = Vehicle::query()->firstOrFail();
    $vehicle->update(['device_id' => '1001']);

    /** @var Shift $shift */
    $shift = Shift::query()->where('vehicle_id', $vehicle->id)->firstOrFail();

    $dispatchedAt = now()->subMinutes(20);

    /** @var Incident $incident */
    $incident = Incident::query()->create([
        'municipio_id' => $user->municipio_id,
        'dispatch_year' => (int) now()->format('Y'),
        'talao' => 880102,
        'status' => IncidentStatus::Dispatched,
        'occurred_at' => now()->subMinutes(25),
        'dispatched_at' => $dispatchedAt,
        'address_line' => 'Rua Teste',
        'city' => 'Demo',
        'patient_call_type' => 'N',
        'primary_shift_id' => $shift->id,
    ]);

    IncidentDispatch::query()->create([
        'municipio_id' => $user->municipio_id,
        'incident_id' => $incident->id,
        'shift_id' => $shift->id,
        'stage' => DispatchStage::Dispatched,
        'dispatched_at' => $dispatchedAt,
    ]);

    Livewire::actingAs($user)
        ->test(IncidentOperationalDetail::class, ['incident' => $incident])
        ->assertSee(__('Percurso da viatura'))
        ->assertSee('US 01')
        ->assertSee('Device 1001')
        ->assertSee(__('Abrir percurso em nova guia'))
        ->assertSeeHtml('data-test="incident-route-map"')
        ->assertSeeHtml('data-test="incident-route-map-fullscreen-link"');
});

test('incident route endpoint uses soft deleted dispatch after incident closure', function (): void {
    /** @var User $user */
    $user = User::query()->where('email', 'municipal@example.com')->firstOrFail();

    /** @var Vehicle $vehicle */
    $vehicle = Vehicle::query()->firstOrFail();
    $vehicle->update(['device_id' => '1001']);

    /** @var Shift $shift */
    $shift = Shift::query()->where('vehicle_id', $vehicle->id)->firstOrFail();

    $dispatchedAt = now()->subHours(2);
    $returnedAt = now()->subHour();

    /** @var Incident $incident */
    $incident = Incident::query()->create([
        'municipio_id' => $user->municipio_id,
        'dispatch_year' => (int) now()->format('Y'),
        'talao' => 880103,
        'status' => IncidentStatus::PendingNurseReport,
        'occurred_at' => now()->subHours(3),
        'dispatched_at' => $dispatchedAt,
        'returned_base_at' => $returnedAt,
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
        'stage' => DispatchStage::ReleasedHospital,
        'is_primary' => true,
        'dispatched_at' => $dispatchedAt,
        'returned_base_at' => $returnedAt,
        'released_at' => $returnedAt,
    ]);

    $dispatch->delete();

    Http::fake([
        'https://traccar.test/api/reports/route*' => Http::response([
            [
                'latitude' => -15.791,
                'longitude' => -47.881,
                'fixTime' => $dispatchedAt->toIso8601String(),
                'speed' => 5,
            ],
        ], 200),
    ]);

    $this->actingAs($user)
        ->getJson(route('operations.incidents.route', $incident))
        ->assertOk()
        ->assertJsonPath('count', 1);

    Livewire::actingAs($user)
        ->test(IncidentOperationalDetail::class, ['incident' => $incident->fresh()])
        ->assertSee('US 01')
        ->assertSee('Device 1001')
        ->assertSee(__('Replay do trajeto registrado no Traccar durante o atendimento.'))
        ->assertDontSee(__('Sem viatura despachada'));
});
