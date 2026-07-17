<?php

declare(strict_types=1);

use App\Domain\Operations\Enums\DispatchStage;
use App\Domain\Operations\Enums\IncidentStatus;
use App\Domain\Operations\Enums\ShiftStatus;
use App\Livewire\Operations\DispatchBoard;
use App\Livewire\Operations\IncidentCreate;
use App\Models\Incident;
use App\Models\IncidentCallRequest;
use App\Models\IncidentDispatch;
use App\Models\IncidentEvent;
use App\Models\Nature;
use App\Models\Shift;
use App\Models\User;
use App\Support\Operations\NearbyIncidentFinder;
use Database\Seeders\OperationalDemoSeeder;
use Livewire\Livewire;

beforeEach(function (): void {
    $this->seed(OperationalDemoSeeder::class);
});

const ANCHOR_LAT = -22.1234567;
const ANCHOR_LNG = -47.1234567;

/** Deslocamento norte/sul em graus para uma distância em metros. */
function latOffsetForMeters(float $meters): float
{
    return $meters / 111_320.0;
}

function makeIncidentAt(User $user, float $lat, float $lng, array $overrides = []): Incident
{
    static $talao = 990000;

    return Incident::query()->create(array_merge([
        'municipio_id' => $user->municipio_id,
        'dispatch_year' => (int) now()->format('Y'),
        'talao' => ++$talao,
        'status' => IncidentStatus::Open,
        'occurred_at' => now()->subMinutes(10),
        'address_line' => 'Rua Âncora',
        'city' => 'Demo',
        'patient_call_type' => 'N',
        'latitude' => $lat,
        'longitude' => $lng,
    ], $overrides));
}

test('finder returns incident inside radius and ignores the one beyond it', function (): void {
    /** @var User $user */
    $user = User::query()->where('email', 'municipal@example.com')->firstOrFail();

    $near = makeIncidentAt($user, ANCHOR_LAT + latOffsetForMeters(150), ANCHOR_LNG);
    $far = makeIncidentAt($user, ANCHOR_LAT + latOffsetForMeters(400), ANCHOR_LNG);

    $found = NearbyIncidentFinder::find(ANCHOR_LAT, ANCHOR_LNG, $user);

    expect($found->pluck('id')->all())->toBe([$near->id])
        ->and($found->first()->distance_meters)->toBeGreaterThan(140)
        ->and($found->first()->distance_meters)->toBeLessThan(160)
        ->and($found->pluck('id'))->not->toContain($far->id);
});

test('finder respects the 200m boundary', function (): void {
    /** @var User $user */
    $user = User::query()->where('email', 'municipal@example.com')->firstOrFail();

    makeIncidentAt($user, ANCHOR_LAT + latOffsetForMeters(199), ANCHOR_LNG);

    expect(NearbyIncidentFinder::find(ANCHOR_LAT, ANCHOR_LNG, $user))->toHaveCount(1);

    Incident::query()->delete();
    makeIncidentAt($user, ANCHOR_LAT + latOffsetForMeters(201), ANCHOR_LNG);

    expect(NearbyIncidentFinder::find(ANCHOR_LAT, ANCHOR_LNG, $user))->toHaveCount(0);
});

test('finder ignores incidents that are no longer active', function (): void {
    /** @var User $user */
    $user = User::query()->where('email', 'municipal@example.com')->firstOrFail();

    foreach ([IncidentStatus::Closed, IncidentStatus::Cancelled, IncidentStatus::Qta, IncidentStatus::Logged] as $status) {
        makeIncidentAt($user, ANCHOR_LAT, ANCHOR_LNG, ['status' => $status]);
    }

    expect(NearbyIncidentFinder::find(ANCHOR_LAT, ANCHOR_LNG, $user))->toHaveCount(0);
});

test('finder ignores incidents outside the time window and without coordinates', function (): void {
    /** @var User $user */
    $user = User::query()->where('email', 'municipal@example.com')->firstOrFail();

    makeIncidentAt($user, ANCHOR_LAT, ANCHOR_LNG, ['occurred_at' => now()->subHours(48)]);
    makeIncidentAt($user, ANCHOR_LAT, ANCHOR_LNG, ['latitude' => null, 'longitude' => null]);

    expect(NearbyIncidentFinder::find(ANCHOR_LAT, ANCHOR_LNG, $user))->toHaveCount(0);
});

test('setting the address warns the operator right away, before any save', function (): void {
    /** @var User $user */
    $user = User::query()->where('email', 'municipal@example.com')->firstOrFail();

    $existing = makeIncidentAt($user, ANCHOR_LAT + latOffsetForMeters(80), ANCHOR_LNG);
    $incidentCountBefore = Incident::query()->count();

    Livewire::actingAs($user)
        ->test(IncidentCreate::class)
        ->set('description', 'Mesmo acidente, outro solicitante')
        ->assertSet('showDuplicateModal', false)
        ->set('latitude', (string) ANCHOR_LAT)
        ->set('longitude', (string) ANCHOR_LNG)
        ->assertHasNoErrors()
        ->assertSet('showDuplicateModal', true)
        ->assertSet('duplicateCandidateIds', [$existing->id])
        ->assertSet('duplicateNotes', 'Mesmo acidente, outro solicitante');

    expect(Incident::query()->count())->toBe($incidentCountBefore);
});

test('the geocode address search also triggers the warning', function (): void {
    /** @var User $user */
    $user = User::query()->where('email', 'municipal@example.com')->firstOrFail();

    $existing = makeIncidentAt($user, ANCHOR_LAT, ANCHOR_LNG);

    // Mesmo efeito da busca por endereço: escreve lat/lng e marca a coordenada como alterada.
    Livewire::actingAs($user)
        ->test(IncidentCreate::class)
        ->set('latitude', (string) (ANCHOR_LAT + latOffsetForMeters(30)))
        ->set('longitude', (string) ANCHOR_LNG)
        ->assertSet('showDuplicateModal', true)
        ->assertSet('duplicateCandidateIds', [$existing->id]);
});

test('dismissing the warning keeps the form usable and never warns again on save', function (): void {
    /** @var User $user */
    $user = User::query()->where('email', 'municipal@example.com')->firstOrFail();
    $nature = Nature::query()->firstOrFail();

    makeIncidentAt($user, ANCHOR_LAT + latOffsetForMeters(80), ANCHOR_LNG);

    Livewire::actingAs($user)
        ->test(IncidentCreate::class)
        ->set('nature_id', $nature->id)
        ->set('description', 'Evento distinto no mesmo quarteirão')
        ->set('address_line', 'Rua Âncora, 10')
        ->set('caller_phone', '11987654321')
        ->set('latitude', (string) ANCHOR_LAT)
        ->set('longitude', (string) ANCHOR_LNG)
        ->assertSet('showDuplicateModal', true)
        ->call('dismissDuplicateModal')
        ->assertSet('showDuplicateModal', false)
        ->call('saveWithCallType', 'N')
        ->assertHasNoErrors()
        ->assertSet('showDuplicateModal', false)
        ->assertRedirect();

    expect(Incident::query()->where('description', 'Evento distinto no mesmo quarteirão')->exists())->toBeTrue()
        ->and(IncidentCallRequest::query()->count())->toBe(0);
});

test('the warning does not reopen while the point stays the same', function (): void {
    /** @var User $user */
    $user = User::query()->where('email', 'municipal@example.com')->firstOrFail();

    makeIncidentAt($user, ANCHOR_LAT, ANCHOR_LNG);

    Livewire::actingAs($user)
        ->test(IncidentCreate::class)
        ->set('latitude', (string) ANCHOR_LAT)
        ->set('longitude', (string) ANCHOR_LNG)
        ->assertSet('showDuplicateModal', true)
        ->call('dismissDuplicateModal')
        ->assertSet('showDuplicateModal', false)
        // Editar outro campo não pode ressuscitar o aviso já dispensado.
        ->set('caller_name', 'Outro solicitante')
        ->assertSet('showDuplicateModal', false)
        // Mover o ponto do mapa é uma decisão nova: avisa de novo.
        ->set('latitude', (string) (ANCHOR_LAT + latOffsetForMeters(20)))
        ->assertSet('showDuplicateModal', true);
});

test('attaching the call to the existing incident registers a request without creating an incident', function (): void {
    /** @var User $user */
    $user = User::query()->where('email', 'municipal@example.com')->firstOrFail();
    $nature = Nature::query()->firstOrFail();

    $existing = makeIncidentAt($user, ANCHOR_LAT + latOffsetForMeters(80), ANCHOR_LNG, [
        'description' => 'Relato inicial do primeiro solicitante.',
    ]);
    $incidentCountBefore = Incident::query()->count();

    Livewire::actingAs($user)
        ->test(IncidentCreate::class)
        ->set('nature_id', $nature->id)
        ->set('description', 'Segunda ligação')
        ->set('address_line', 'Rua Âncora, 10')
        ->set('caller_name', 'Maria')
        ->set('caller_phone', '11912345678')
        ->set('latitude', (string) ANCHOR_LAT)
        ->set('longitude', (string) ANCHOR_LNG)
        ->assertSet('showDuplicateModal', true)
        ->set('duplicateNotes', 'Vítima consciente agora.')
        ->call('attachRequestToIncident', $existing->id)
        ->assertHasNoErrors()
        ->assertSet('showDuplicateModal', false)
        ->assertRedirect(route('operations.incidents.show', $existing));

    expect(Incident::query()->count())->toBe($incidentCountBefore);

    /** @var IncidentCallRequest $request */
    $request = IncidentCallRequest::query()->where('incident_id', $existing->id)->sole();

    expect($request->caller_name)->toBe('Maria')
        ->and($request->caller_phone)->toBe('11912345678')
        ->and($request->notes)->toBe('Vítima consciente agora.')
        ->and($request->created_by)->toBe($user->id)
        ->and($request->distance_meters)->toBeGreaterThan(70)
        ->and($request->distance_meters)->toBeLessThan(90);

    $existing->refresh();

    expect($existing->description)
        ->toContain('Relato inicial do primeiro solicitante.')
        ->toContain('Vítima consciente agora.')
        ->toContain($user->name)
        ->and($existing->totalCallRequestCount())->toBe(2);

    expect(IncidentEvent::query()
        ->where('incident_id', $existing->id)
        ->where('event_key', 'incident_call_request_registered')
        ->exists())->toBeTrue();
});

test('attaching without a phone number is refused', function (): void {
    /** @var User $user */
    $user = User::query()->where('email', 'municipal@example.com')->firstOrFail();

    $existing = makeIncidentAt($user, ANCHOR_LAT, ANCHOR_LNG);

    Livewire::actingAs($user)
        ->test(IncidentCreate::class)
        ->set('latitude', (string) ANCHOR_LAT)
        ->set('longitude', (string) ANCHOR_LNG)
        ->assertSet('showDuplicateModal', true)
        ->call('attachRequestToIncident', $existing->id)
        ->assertHasErrors('caller_phone');

    expect(IncidentCallRequest::query()->count())->toBe(0);
});

test('incident without coordinates skips the duplicate warning', function (): void {
    /** @var User $user */
    $user = User::query()->where('email', 'municipal@example.com')->firstOrFail();
    $nature = Nature::query()->firstOrFail();

    makeIncidentAt($user, ANCHOR_LAT, ANCHOR_LNG);

    Livewire::actingAs($user)
        ->test(IncidentCreate::class)
        ->set('nature_id', $nature->id)
        ->set('description', 'Sem coordenadas')
        ->set('address_line', 'Rua Sem Geo')
        ->set('caller_phone', '11987654321')
        ->call('saveWithCallType', 'N')
        ->assertHasNoErrors()
        ->assertSet('showDuplicateModal', false)
        ->assertRedirect();
});

test('the guest form never warns, since the caller cannot triage the event', function (): void {
    /** @var User $user */
    $user = User::query()->where('email', 'municipal@example.com')->firstOrFail();

    makeIncidentAt($user, ANCHOR_LAT, ANCHOR_LNG);

    Livewire::actingAs($user)
        ->test(IncidentCreate::class, ['guest_intake' => true])
        ->set('latitude', (string) ANCHOR_LAT)
        ->set('longitude', (string) ANCHOR_LNG)
        ->assertSet('showDuplicateModal', false)
        ->assertSet('duplicateCandidateIds', []);
});

test('dispatch queue shows the total request count for the point', function (): void {
    /** @var User $user */
    $user = User::query()->where('email', 'municipal@example.com')->firstOrFail();

    $incident = makeIncidentAt($user, ANCHOR_LAT, ANCHOR_LNG);

    IncidentCallRequest::query()->create([
        'incident_id' => $incident->id,
        'caller_name' => 'João',
        'caller_phone' => '11999998888',
        'created_by' => $user->id,
    ]);
    IncidentCallRequest::query()->create([
        'incident_id' => $incident->id,
        'caller_name' => 'Ana',
        'created_by' => $user->id,
    ]);

    // 1 chamada original + 2 solicitações adicionais.
    Livewire::actingAs($user)
        ->test(DispatchBoard::class)
        ->assertSee('3 solicitações para este ponto')
        ->call('openDetailModal', $incident->id)
        ->assertSee('João')
        ->assertSee('Ana');
});

test('duplicate warning surfaces the vehicle assigned to an incident already being attended', function (): void {
    /** @var User $user */
    $user = User::query()->where('email', 'municipal@example.com')->firstOrFail();
    $nature = Nature::query()->firstOrFail();

    /** @var Shift $shift */
    $shift = Shift::query()->where('municipio_id', $user->municipio_id)->firstOrFail();
    $shift->update(['status' => ShiftStatus::Empenhado, 'status_legacy' => 2]);

    $existing = makeIncidentAt($user, ANCHOR_LAT + latOffsetForMeters(50), ANCHOR_LNG, [
        'status' => IncidentStatus::InProgress,
        'primary_shift_id' => $shift->id,
    ]);

    IncidentDispatch::query()->create([
        'municipio_id' => $user->municipio_id,
        'incident_id' => $existing->id,
        'shift_id' => $shift->id,
        'stage' => DispatchStage::Dispatched,
    ]);

    Livewire::actingAs($user)
        ->test(IncidentCreate::class)
        ->set('nature_id', $nature->id)
        ->set('address_line', 'Rua Âncora, 10')
        ->set('caller_phone', '11987654321')
        ->set('latitude', (string) ANCHOR_LAT)
        ->set('longitude', (string) ANCHOR_LNG)
        ->assertSet('showDuplicateModal', true)
        ->assertSee('Em atendimento')
        ->assertSee($shift->vehicle->prefix);
});
