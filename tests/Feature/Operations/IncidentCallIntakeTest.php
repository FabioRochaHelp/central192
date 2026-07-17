<?php

declare(strict_types=1);

use App\Domain\Operations\Enums\OperationalCallAlertStatus;
use App\Domain\Operations\Events\OperationalCallAlertClusterUpdated;
use App\Domain\Operations\Events\OperationalCallAlertReceived;
use App\Domain\Operations\Events\OperationalCallIntakeReceived;
use App\Livewire\Operations\IncidentCallStart;
use App\Livewire\Operations\IncidentCreate;
use App\Livewire\Operations\OperationalCallIntakeBridge;
use App\Livewire\Operations\TacticalMap;
use App\Models\Nature;
use App\Models\OperationalCallAlert;
use App\Models\User;
use App\Support\Operations\OperationalCallAlertGrouper;
use Database\Seeders\OperationalDemoSeeder;
use Illuminate\Support\Facades\Event;
use Livewire\Livewire;

beforeEach(function (): void {
    $this->seed(OperationalDemoSeeder::class);
});

// ---------------------------------------------------------------------------
// Validação de acesso ao webhook
// ---------------------------------------------------------------------------

test('webhook ping returns 200 on GET request', function (): void {
    $this->getJson('/integrations/calls/incident-intake')
        ->assertOk()
        ->assertJson(['status' => 'ok', 'webhook' => 'incident-intake']);
});

test('webhook returns 422 when secret is not configured', function (): void {
    config(['operations.call_webhook_secret' => '', 'app.env' => 'testing']);

    $this->postJson('/integrations/calls/incident-intake', [
        'type' => 'incident',
        'phone' => '11987654321',
    ])
        ->assertStatus(422);
});

test('webhook returns 503 when secret is not configured in production', function (): void {
    config(['operations.call_webhook_secret' => '', 'app.env' => 'production']);

    $this->postJson('/integrations/calls/incident-intake', [
        'type' => 'incident',
        'phone' => '11987654321',
    ])
        ->assertStatus(503);
});

test('webhook returns 401 when X-Webhook-Secret header is missing', function (): void {
    config(['operations.call_webhook_secret' => 'configured']);

    $this->postJson('/integrations/calls/incident-intake', [
        'type' => 'incident',
        'phone' => '11987654321',
    ])
        ->assertUnauthorized();
});

test('webhook rejects request without valid secret', function (): void {
    config(['operations.call_webhook_secret' => 'configured']);

    $this->postJson('/integrations/calls/incident-intake', [
        'type' => 'incident',
        'phone' => '11987654321',
    ], ['X-Webhook-Secret' => 'wrong'])
        ->assertForbidden();
});

test('webhook rejects phone shorter than 8 digits', function (): void {
    config(['operations.call_webhook_secret' => 'test-secret']);

    $this->postJson('/integrations/calls/incident-intake', [
        'type' => 'incident',
        'phone' => '1234567',
    ], ['X-Webhook-Secret' => 'test-secret'])
        ->assertUnprocessable();
});

test('webhook rejects missing phone field', function (): void {
    config(['operations.call_webhook_secret' => 'test-secret']);

    $this->postJson('/integrations/calls/incident-intake', [
        'type' => 'incident',
    ], ['X-Webhook-Secret' => 'test-secret'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['phone']);
});

test('webhook rejects missing type field', function (): void {
    config(['operations.call_webhook_secret' => 'test-secret']);

    $this->postJson('/integrations/calls/incident-intake', [
        'phone' => '11987654321',
    ], ['X-Webhook-Secret' => 'test-secret'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['type']);
});

test('webhook rejects invalid type value', function (): void {
    config(['operations.call_webhook_secret' => 'test-secret']);

    $this->postJson('/integrations/calls/incident-intake', [
        'type' => 'unknown',
        'phone' => '11987654321',
    ], ['X-Webhook-Secret' => 'test-secret'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['type']);
});

test('webhook rejects latitude out of range', function (): void {
    config(['operations.call_webhook_secret' => 'test-secret']);

    $this->postJson('/integrations/calls/incident-intake', [
        'type' => 'incident',
        'phone' => '11987654321',
        'latitude' => 999,
    ], ['X-Webhook-Secret' => 'test-secret'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['latitude']);
});

test('webhook type incident returns signed form url and broadcasts modal intake', function (): void {
    Event::fake([OperationalCallIntakeReceived::class]);
    config([
        'operations.call_webhook_secret' => 'test-secret',
        'operations.broadcast_call_intake' => true,
    ]);

    $response = $this->postJson('/integrations/calls/incident-intake', [
        'type' => 'incident',
        'phone' => '+55 (11) 98765-4321',
        'caller_name' => 'Maria',
        'latitude' => -23.55,
        'longitude' => -46.63,
        'external_reference' => 'PBX-999',
    ], ['X-Webhook-Secret' => 'test-secret']);

    $response->assertOk()
        ->assertJson([
            'type' => 'incident',
        ])
        ->assertJsonStructure(['form_url', 'expires_at']);

    Event::assertDispatched(function (OperationalCallIntakeReceived $e): bool {
        return $e->callerName === 'Maria'
            && $e->latitude === '-23.55'
            && $e->longitude === '-46.63'
            && $e->externalReference === 'PBX-999';
    });

    $url = (string) $response->json('form_url');
    expect($url)->toContain('signature=');

    $this->get($url)
        ->assertOk()
        ->assertSee('11987654321', false)
        ->assertSee('Maria', false)
        ->assertSee('PBX-999', false);
});

test('webhook type alert requires coordinates and creates pending alert on map', function (): void {
    Event::fake([OperationalCallAlertReceived::class, OperationalCallAlertClusterUpdated::class]);
    config([
        'operations.call_webhook_secret' => 'test-secret',
        'operations.broadcast_call_intake' => true,
    ]);

    $this->postJson('/integrations/calls/incident-intake', [
        'type' => 'alert',
        'phone' => '11987654321',
    ], ['X-Webhook-Secret' => 'test-secret'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['latitude', 'longitude']);

    $response = $this->postJson('/integrations/calls/incident-intake', [
        'type' => 'alert',
        'phone' => '18988138348',
        'caller_name' => 'Fabio Rocha',
        'latitude' => -22.629273922333702,
        'longitude' => -50.409406732787716,
        'call_received_at' => '2026-05-09 10:30:00',
        'external_reference' => 'SATELITE GOES',
        'metadata' => [
            'temperature' => 435.32,
            'humidity' => 45.5,
            'wind_speed' => 12.3,
            'wind_direction' => 180.0,
            'wind_direction_text' => 'S',
            'air_temperature' => 28.4,
            'rain' => 0.0,
        ],
    ], ['X-Webhook-Secret' => 'test-secret']);

    $response->assertOk()
        ->assertJson([
            'type' => 'alert',
        ])
        ->assertJsonStructure(['alert_id', 'expires_at']);

    $alertId = (string) $response->json('alert_id');

    $this->assertDatabaseHas('operational_call_alerts', [
        'id' => $alertId,
        'phone' => '18988138348',
        'caller_name' => 'Fabio Rocha',
        'external_reference' => 'SATELITE GOES',
        'status' => OperationalCallAlertStatus::Pending->value,
    ]);

    /** @var OperationalCallAlert $alert */
    $alert = OperationalCallAlert::query()->findOrFail($alertId);
    expect($alert->resolvedTemperature())->toBe(435.32)
        ->and($alert->metadata)->toMatchArray([
            'temperature' => 435.32,
            'humidity' => 45.5,
            'wind_speed' => 12.3,
            'wind_direction' => 180.0,
            'wind_direction_text' => 'S',
            'air_temperature' => 28.4,
            'rain' => 0.0,
        ])
        ->and($alert->expires_at->isAfter(now()->addDays(2)))->toBeTrue()
        ->and($alert->expires_at->isBefore(now()->addDays(3)->addMinute()))->toBeTrue();

    Event::assertDispatched(function (OperationalCallAlertReceived $e) use ($alertId): bool {
        return $e->alert->id === $alertId
            && $e->alert->caller_name === 'Fabio Rocha'
            && $e->alert->resolvedTemperature() === 435.32
            && (float) $e->alert->latitude === -22.6292739;
    });

    Event::assertDispatched(function (OperationalCallAlertClusterUpdated $e): bool {
        return $e->payload['count'] === 1
            && $e->payload['monitoring'] === false;
    });
});

test('guest can submit incident from signed webhook url without login', function (): void {
    Event::fake([OperationalCallIntakeReceived::class]);
    config(['operations.call_webhook_secret' => 'test-secret']);

    $response = $this->postJson('/integrations/calls/incident-intake', [
        'type' => 'incident',
        'phone' => '11998877666',
    ], ['X-Webhook-Secret' => 'test-secret']);

    $url = (string) $response->json('form_url');
    $this->get($url)->assertOk();

    /** @var Nature $nature */
    $nature = Nature::query()->firstOrFail();

    Livewire::test(IncidentCreate::class)
        ->set('occurred_at', now()->format('Y-m-d\\TH:i'))
        ->set('nature_id', $nature->id)
        ->set('description', 'Paciente com dor torácica.')
        ->set('address_line', 'Rua de Teste, 100')
        ->set('caller_phone', '11998877666')
        ->call('saveWithCallType', 'U')
        ->assertHasNoErrors()
        ->assertRedirect(route('operations.incidents.registered-guest'));

    $this->get(route('operations.incidents.registered-guest'))
        ->assertOk()
        ->assertSee('Talão', false);
});

test('manual call start stores phone in session for create form', function (): void {
    /** @var User $central */
    $central = User::query()->where('email', 'central@example.com')->firstOrFail();

    Livewire::actingAs($central)
        ->test(IncidentCallStart::class)
        ->set('caller_phone', '(11) 3333-4444')
        ->call('continueToForm')
        ->assertHasNoErrors()
        ->assertRedirect(route('operations.incidents.create'));

    $this->actingAs($central)
        ->get(route('operations.incidents.create'))
        ->assertOk()
        ->assertSee('1133334444', false);
});

test('operational bridge accepts Laravel broadcast socket extra argument', function (): void {
    /** @var User $central */
    $central = User::query()->where('email', 'central@example.com')->firstOrFail();

    Livewire::actingAs($central)
        ->test(OperationalCallIntakeBridge::class)
        ->call(
            'openOperationalCallIntakeFromBroadcast',
            form_url: 'https://example.test/signed',
            phone: '11888877777',
            expires_at: now()->addMinutes(30)->toIso8601String(),
            socket: 'mock-socket-id',
        )
        ->assertSet('callIntakePrefill.phone', '11888877777')
        ->assertSet('showCallIntakeModal', true);
});

test('operational bridge normalizes numeric phone from echo-shaped payload', function (): void {
    /** @var User $central */
    $central = User::query()->where('email', 'central@example.com')->firstOrFail();

    Livewire::actingAs($central)
        ->test(OperationalCallIntakeBridge::class)
        ->call('openOperationalCallIntakeFromBroadcast', phone: 11_988_877_777)
        ->assertSet('callIntakePrefill.phone', '11988877777');
});

test('operational bridge opens modal when receiving operational call intake payload', function (): void {
    /** @var User $central */
    $central = User::query()->where('email', 'central@example.com')->firstOrFail();

    Livewire::actingAs($central)
        ->test(OperationalCallIntakeBridge::class)
        ->call(
            'openOperationalCallIntakeFromBroadcast',
            form_url: 'https://example.test/signed',
            phone: '11888877777',
            expires_at: now()->addMinutes(30)->toIso8601String(),
            caller_name: 'João',
            latitude: '-10.5',
            longitude: '-20.25',
            call_received_at: now()->toIso8601String(),
            external_reference: 'PBX-X',
            alert_id: 'alert-uuid-1',
        )
        ->assertSet('showCallIntakeModal', true)
        ->assertSet('callIntakePrefill.phone', '11888877777')
        ->assertSet('callIntakePrefill.caller_name', 'João')
        ->assertSet('callIntakePrefill.alert_id', 'alert-uuid-1');
});

test('incident create embedded in modal receives caller phone from props', function (): void {
    /** @var User $central */
    $central = User::query()->where('email', 'central@example.com')->firstOrFail();

    Livewire::actingAs($central)
        ->test(IncidentCreate::class, [
            'embeddedInModal' => true,
            'caller_phone' => '11988776655',
        ])
        ->assertSet('caller_phone', '11988776655')
        ->assertSet('embeddedInModal', true);
});

test('alerts at same location are grouped with lilac monitoring flag', function (): void {
    OperationalCallAlert::factory()->create([
        'phone' => '11911110001',
        'latitude' => -23.5500000,
        'longitude' => -46.6300000,
    ]);
    OperationalCallAlert::factory()->create([
        'phone' => '11911110002',
        'latitude' => -23.5500000,
        'longitude' => -46.6300000,
    ]);

    $clusters = OperationalCallAlertGrouper::allClusters();

    expect($clusters)->toHaveCount(1)
        ->and($clusters[0]['count'])->toBe(2)
        ->and($clusters[0]['monitoring'])->toBeTrue()
        ->and($clusters[0]['alerts'])->toHaveCount(2);
});

test('tactical map aborts entire alert cluster at location', function (): void {
    Event::fake([OperationalCallAlertClusterUpdated::class]);

    /** @var User $central */
    $central = User::query()->where('email', 'central@example.com')->firstOrFail();

    $alertA = OperationalCallAlert::factory()->create([
        'latitude' => -23.5500000,
        'longitude' => -46.6300000,
    ]);
    $alertB = OperationalCallAlert::factory()->create([
        'latitude' => -23.5500000,
        'longitude' => -46.6300000,
    ]);

    $locationKey = OperationalCallAlertGrouper::locationKey(-23.55, -46.63);

    Livewire::actingAs($central)
        ->test(TacticalMap::class)
        ->call('abortCallAlertCluster', locationKey: $locationKey)
        ->assertHasNoErrors();

    expect($alertA->fresh()?->status)->toBe(OperationalCallAlertStatus::Aborted)
        ->and($alertB->fresh()?->status)->toBe(OperationalCallAlertStatus::Aborted);

    Event::assertDispatched(function (OperationalCallAlertClusterUpdated $e) use ($locationKey): bool {
        return ($e->payload['remove'] ?? false) === true
            && $e->payload['location_key'] === $locationKey;
    });
});

test('tactical map opens incident modal from alert cluster using latest call', function (): void {
    /** @var User $central */
    $central = User::query()->where('email', 'central@example.com')->firstOrFail();

    OperationalCallAlert::factory()->create([
        'phone' => '11900001111',
        'latitude' => -23.5500000,
        'longitude' => -46.6300000,
        'created_at' => now()->subMinute(),
    ]);
    $latest = OperationalCallAlert::factory()->create([
        'phone' => '11900002222',
        'latitude' => -23.5500000,
        'longitude' => -46.6300000,
        'created_at' => now(),
    ]);

    $locationKey = OperationalCallAlertGrouper::locationKey(-23.55, -46.63);

    Livewire::actingAs($central)
        ->test(TacticalMap::class)
        ->call('createIncidentFromCallAlertCluster', locationKey: $locationKey)
        ->assertDispatched('operational-call-intake');

    expect(OperationalCallAlertGrouper::latestActiveAtLocationKey($locationKey)?->id)->toBe($latest->id);
});

test('incident create from alert converts all alerts at the same location', function (): void {
    Event::fake([OperationalCallAlertClusterUpdated::class]);

    /** @var User $central */
    $central = User::query()->where('email', 'central@example.com')->firstOrFail();

    $alertA = OperationalCallAlert::factory()->create([
        'phone' => '11977776661',
        'latitude' => -23.5500000,
        'longitude' => -46.6300000,
    ]);
    $alertB = OperationalCallAlert::factory()->create([
        'phone' => '11977776662',
        'latitude' => -23.5500000,
        'longitude' => -46.6300000,
    ]);

    /** @var Nature $nature */
    $nature = Nature::query()->firstOrFail();

    Livewire::actingAs($central)
        ->test(IncidentCreate::class, [
            'embeddedInModal' => true,
            'caller_phone' => '11977776662',
            'intake_alert_id' => $alertB->id,
            'latitude' => (string) $alertB->latitude,
            'longitude' => (string) $alertB->longitude,
        ])
        ->set('occurred_at', now()->format('Y-m-d\\TH:i'))
        ->set('nature_id', $nature->id)
        ->set('description', 'Uma ocorrência para o ponto.')
        ->set('address_line', 'Rua Alerta, 10')
        ->call('saveWithCallType', 'U')
        ->assertHasNoErrors()
        ->assertDispatched('call-intake-incident-saved');

    $incidentId = $alertB->fresh()?->converted_incident_id;

    expect($alertA->fresh()?->status)->toBe(OperationalCallAlertStatus::Converted)
        ->and($alertB->fresh()?->status)->toBe(OperationalCallAlertStatus::Converted)
        ->and($alertA->fresh()?->converted_incident_id)->toBe($incidentId)
        ->and($alertB->fresh()?->converted_incident_id)->toBe($incidentId);

    Event::assertDispatched(function (OperationalCallAlertClusterUpdated $e): bool {
        return ($e->payload['remove'] ?? false) === true;
    });
});

test('incident create from alert converts alert and updates cluster', function (): void {
    Event::fake([OperationalCallAlertClusterUpdated::class]);

    /** @var User $central */
    $central = User::query()->where('email', 'central@example.com')->firstOrFail();

    /** @var OperationalCallAlert $alert */
    $alert = OperationalCallAlert::factory()->create([
        'phone' => '11977776666',
    ]);

    /** @var Nature $nature */
    $nature = Nature::query()->firstOrFail();

    Livewire::actingAs($central)
        ->test(IncidentCreate::class, [
            'embeddedInModal' => true,
            'caller_phone' => '11977776666',
            'intake_alert_id' => $alert->id,
            'latitude' => (string) $alert->latitude,
            'longitude' => (string) $alert->longitude,
        ])
        ->set('occurred_at', now()->format('Y-m-d\\TH:i'))
        ->set('nature_id', $nature->id)
        ->set('description', 'Conversão de alerta.')
        ->set('address_line', 'Rua Alerta, 10')
        ->call('saveWithCallType', 'U')
        ->assertHasNoErrors()
        ->assertDispatched('call-intake-incident-saved');

    expect($alert->fresh()?->status)->toBe(OperationalCallAlertStatus::Converted)
        ->and($alert->fresh()?->converted_incident_id)->not->toBeNull();

    Event::assertDispatched(function (OperationalCallAlertClusterUpdated $e) use ($alert): bool {
        return ($e->payload['remove'] ?? false) === true
            && $e->payload['location_key'] === OperationalCallAlertGrouper::locationKey(
                (float) $alert->latitude,
                (float) $alert->longitude,
            );
    });
});

test('tactical map render includes alert clusters', function (): void {
    /** @var User $central */
    $central = User::query()->where('email', 'central@example.com')->firstOrFail();

    OperationalCallAlert::factory()->create([
        'phone' => '11911112222',
        'external_reference' => 'Sensor A',
        'metadata' => ['temperature' => 120.5],
    ]);
    OperationalCallAlert::factory()->aborted()->create();

    Livewire::actingAs($central)
        ->test(TacticalMap::class)
        ->assertSee(route('operations.cadastro.bases.geojson'), false)
        ->assertViewHas('mapAlertClusters', function (array $clusters): bool {
            return collect($clusters)->contains(
                fn (array $c): bool => collect($c['alerts'] ?? [])->contains(
                    fn (array $a): bool => ($a['external_reference'] ?? '') === 'Sensor A'
                        && ($a['temperature'] ?? null) === 120.5,
                ),
            );
        });
});
