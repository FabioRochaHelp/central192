<?php

declare(strict_types=1);

use App\Domain\Operations\Actions\AppendIncidentDescriptionAction;
use App\Domain\Operations\Actions\RegisterIncidentCallRequestAction;
use App\Domain\Operations\DTOs\RegisterIncidentCallRequestDTO;
use App\Domain\Operations\Enums\DispatchStage;
use App\Domain\Operations\Enums\IncidentStatus;
use App\Domain\Operations\Enums\ShiftStatus;
use App\Livewire\Operations\DispatchBoard;
use App\Models\Incident;
use App\Models\IncidentDispatch;
use App\Models\Shift;
use App\Models\User;
use App\Models\Vehicle;
use App\Support\Operations\DispatchNoteSignal;
use Database\Seeders\OperationalDemoSeeder;
use Livewire\Livewire;

beforeEach(function (): void {
    $this->seed(OperationalDemoSeeder::class);
});

/** @return array{0: User, 1: Incident, 2: IncidentDispatch} */
function dispatchedIncidentFixture(int $talao = 960001): array
{
    /** @var User $user */
    $user = User::query()->where('email', 'municipal@example.com')->firstOrFail();

    /** @var Shift $shift */
    $shift = Shift::query()->where('municipio_id', $user->municipio_id)->firstOrFail();
    $shift->update(['status' => ShiftStatus::Empenhado, 'status_legacy' => 2]);

    /** @var Incident $incident */
    $incident = Incident::query()->create([
        'municipio_id' => $user->municipio_id,
        'dispatch_year' => (int) now()->format('Y'),
        'talao' => $talao,
        'status' => IncidentStatus::InProgress,
        'occurred_at' => now()->subMinutes(30),
        'address_line' => 'Rua do Sinal',
        'city' => 'Demo',
        'patient_call_type' => 'N',
        'latitude' => -22.1234567,
        'longitude' => -47.1234567,
        'description' => 'Relato inicial.',
        'primary_shift_id' => $shift->id,
    ]);

    /** @var IncidentDispatch $dispatch */
    $dispatch = IncidentDispatch::query()->create([
        'municipio_id' => $user->municipio_id,
        'incident_id' => $incident->id,
        'shift_id' => $shift->id,
        'stage' => DispatchStage::Dispatched,
    ]);

    return [$user, $incident, $dispatch];
}

test('a freshly dispatched unit has no unseen notes', function (): void {
    [, , $dispatch] = dispatchedIncidentFixture();

    expect(DispatchNoteSignal::unseenCountFor($dispatch))->toBe(0);
});

test('descriptions added after the dispatch count as unseen', function (): void {
    [$user, $incident, $dispatch] = dispatchedIncidentFixture();

    app(AppendIncidentDescriptionAction::class)->execute($incident, 'Vítima consciente.', $user);
    app(AppendIncidentDescriptionAction::class)->execute($incident, 'Trânsito parado.', $user);

    expect(DispatchNoteSignal::unseenCountFor($dispatch->fresh()))->toBe(2);
});

test('a description written before the dispatch is not signalled', function (): void {
    /** @var User $user */
    $user = User::query()->where('email', 'municipal@example.com')->firstOrFail();

    /** @var Shift $shift */
    $shift = Shift::query()->where('municipio_id', $user->municipio_id)->firstOrFail();

    /** @var Incident $incident */
    $incident = Incident::query()->create([
        'municipio_id' => $user->municipio_id,
        'dispatch_year' => (int) now()->format('Y'),
        'talao' => 960009,
        'status' => IncidentStatus::Open,
        'occurred_at' => now()->subHour(),
        'address_line' => 'Rua do Sinal',
        'city' => 'Demo',
        'patient_call_type' => 'N',
    ]);

    // Anotação anterior ao empenho: a guarnição já saiu com essa informação.
    $this->travelTo(now()->subMinutes(20));
    app(AppendIncidentDescriptionAction::class)->execute($incident, 'Anotação antes do empenho.', $user);
    $this->travelBack();

    /** @var IncidentDispatch $dispatch */
    $dispatch = IncidentDispatch::query()->create([
        'municipio_id' => $user->municipio_id,
        'incident_id' => $incident->id,
        'shift_id' => $shift->id,
        'stage' => DispatchStage::Dispatched,
    ]);

    expect(DispatchNoteSignal::unseenCountFor($dispatch))->toBe(0);
});

test('a new call request at the same point signals the dispatched unit', function (): void {
    [$user, $incident, $dispatch] = dispatchedIncidentFixture();

    app(RegisterIncidentCallRequestAction::class)->execute(
        $incident,
        new RegisterIncidentCallRequestDTO(
            callerName: 'Vizinho',
            callerPhone: '11999998888',
            notes: 'Fogo aumentou.',
        ),
        $user,
    );

    expect(DispatchNoteSignal::unseenCountFor($dispatch->fresh()))->toBe(1);
});

test('a call request without notes does not signal, since nothing was written', function (): void {
    [$user, $incident, $dispatch] = dispatchedIncidentFixture();

    app(RegisterIncidentCallRequestAction::class)->execute(
        $incident,
        new RegisterIncidentCallRequestDTO(callerPhone: '11999998888'),
        $user,
    );

    expect(DispatchNoteSignal::unseenCountFor($dispatch->fresh()))->toBe(0);
});

test('opening the balloon marks the notes as relayed and opens the description modal', function (): void {
    [$user, $incident, $dispatch] = dispatchedIncidentFixture();

    app(AppendIncidentDescriptionAction::class)->execute($incident, 'Nova informação.', $user);

    expect(DispatchNoteSignal::unseenCountFor($dispatch->fresh()))->toBe(1);

    // O despachador lê o board e clica depois — nunca no mesmo instante da anotação.
    $this->travelTo(now()->addSeconds(5));

    Livewire::actingAs($user)
        ->test(DispatchBoard::class)
        ->assertSee('anotação nova para repassar à guarnição')
        ->call('openNotesFromKanbanCard', $dispatch->id)
        ->assertSet('showObservationModal', true)
        ->assertSet('actionIncidentId', $incident->id);

    expect($dispatch->fresh()->notes_seen_at)->not->toBeNull()
        ->and(DispatchNoteSignal::unseenCountFor($dispatch->fresh()))->toBe(0);

    $this->travelBack();
});

test('a note tied to the very second of the click keeps the balloon on, never dropping it', function (): void {
    [$user, $incident, $dispatch] = dispatchedIncidentFixture();

    // Empate proposital: `recorded_at` e `notes_seen_at` têm precisão de segundo.
    app(AppendIncidentDescriptionAction::class)->execute($incident, 'Escrita no mesmo segundo do clique.', $user);
    DispatchNoteSignal::markSeen($dispatch);

    // Preferimos um clique a mais a perder a anotação silenciosamente.
    expect(DispatchNoteSignal::unseenCountFor($dispatch->fresh()))->toBe(1);

    $this->travelTo(now()->addSeconds(2));
    DispatchNoteSignal::markSeen($dispatch);
    expect(DispatchNoteSignal::unseenCountFor($dispatch->fresh()))->toBe(0);
    $this->travelBack();
});

test('the balloon lights up again on the next note', function (): void {
    [$user, $incident, $dispatch] = dispatchedIncidentFixture();

    app(AppendIncidentDescriptionAction::class)->execute($incident, 'Primeira.', $user);

    $this->travelTo(now()->addSeconds(5));
    DispatchNoteSignal::markSeen($dispatch);

    expect(DispatchNoteSignal::unseenCountFor($dispatch->fresh()))->toBe(0);

    $this->travelTo(now()->addMinute());
    app(AppendIncidentDescriptionAction::class)->execute($incident, 'Segunda.', $user);

    expect(DispatchNoteSignal::unseenCountFor($dispatch->fresh()))->toBe(1);

    $this->travelBack();
});

test('each vehicle on the incident carries its own signal', function (): void {
    [$user, $incident, $firstDispatch] = dispatchedIncidentFixture();

    /** @var Vehicle $otherVehicle */
    $otherVehicle = Vehicle::query()->create([
        'municipio_id' => $user->municipio_id,
        'plate' => 'XYZ9W88',
        'prefix' => 'US 02',
        'make' => 'Demo',
        'model' => 'Ambulância',
        'year' => (int) date('Y'),
    ]);

    /** @var Shift $otherShift */
    $otherShift = Shift::query()->create([
        'municipio_id' => $user->municipio_id,
        'vehicle_id' => $otherVehicle->id,
        'starts_at' => now()->subHour(),
        'ends_at' => now()->addHours(18),
        'status' => ShiftStatus::Empenhado,
        'status_legacy' => 2,
    ]);

    /** @var IncidentDispatch $secondDispatch */
    $secondDispatch = IncidentDispatch::query()->create([
        'municipio_id' => $user->municipio_id,
        'incident_id' => $incident->id,
        'shift_id' => $otherShift->id,
        'stage' => DispatchStage::Dispatched,
    ]);

    app(AppendIncidentDescriptionAction::class)->execute($incident, 'Vale para as duas.', $user);

    $this->travelTo(now()->addSeconds(5));
    DispatchNoteSignal::markSeen($firstDispatch);

    // Uma viatura ter recebido o recado não apaga o sinal da outra.
    expect(DispatchNoteSignal::unseenCountFor($firstDispatch->fresh()))->toBe(0)
        ->and(DispatchNoteSignal::unseenCountFor($secondDispatch->fresh()))->toBe(1);

    $this->travelBack();
});

test('unseen counts for the whole board resolve without an N+1', function (): void {
    [$user, $incident, $dispatch] = dispatchedIncidentFixture();
    [, $incidentB, $dispatchB] = dispatchedIncidentFixture(960002);

    app(AppendIncidentDescriptionAction::class)->execute($incident, 'A', $user);
    app(AppendIncidentDescriptionAction::class)->execute($incidentB, 'B1', $user);
    app(AppendIncidentDescriptionAction::class)->execute($incidentB, 'B2', $user);

    $dispatches = IncidentDispatch::query()->whereIn('id', [$dispatch->id, $dispatchB->id])->get();

    DB::enableQueryLog();
    $counts = DispatchNoteSignal::unseenCountsByDispatchId($dispatches);
    $queries = DB::getQueryLog();
    DB::disableQueryLog();

    expect($counts)->toBe([$dispatch->id => 1, $dispatchB->id => 2])
        ->and($queries)->toHaveCount(1);
});
