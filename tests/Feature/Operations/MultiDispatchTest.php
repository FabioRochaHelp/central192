<?php

declare(strict_types=1);

use App\Domain\Operations\Actions\AdvanceDispatchStageAction;
use App\Domain\Operations\Actions\CancelDispatchAtSceneAction;
use App\Domain\Operations\Actions\DispatchUnitAction;
use App\Domain\Operations\Actions\FinalizeIncidentClosureAction;
use App\Domain\Operations\Actions\ReleaseUnitAction;
use App\Domain\Operations\DTOs\AdvanceDispatchStageDTO;
use App\Domain\Operations\DTOs\DispatchUnitDTO;
use App\Domain\Operations\DTOs\FinalizeIncidentClosureDTO;
use App\Domain\Operations\DTOs\ReleaseUnitDTO;
use App\Domain\Operations\Enums\DispatchSceneCancelReason;
use App\Domain\Operations\Enums\DispatchStage;
use App\Domain\Operations\Enums\IncidentStatus;
use App\Domain\Operations\Enums\ShiftStatus;
use App\Models\Incident;
use App\Models\IncidentDispatch;
use App\Models\Shift;
use App\Models\User;
use App\Models\Vehicle;
use Database\Seeders\OperationalDemoSeeder;

function createDemoShift(int $municipioId, string $prefix, string $plate): Shift
{
    $vehicle = Vehicle::query()->create([
        'municipio_id' => $municipioId,
        'plate' => $plate,
        'prefix' => $prefix,
        'make' => 'Demo',
        'model' => 'Ambulância',
        'year' => (int) now()->format('Y'),
        'device_id' => null,
    ]);

    return Shift::query()->create([
        'municipio_id' => $municipioId,
        'vehicle_id' => $vehicle->id,
        'starts_at' => now()->subHour(),
        'ends_at' => now()->addHours(18),
        'status' => ShiftStatus::Disponivel,
        'status_legacy' => 1,
        'available_at' => now()->subHour(),
    ]);
}

/**
 * @param  array<int, DispatchStage>  $through
 */
function advanceDispatchThroughStages(IncidentDispatch $dispatch, array $through, int $operatorUserId): IncidentDispatch
{
    $current = $dispatch->fresh();

    foreach ($through as $target) {
        if ($current->stage === $target) {
            continue;
        }

        $current = app(AdvanceDispatchStageAction::class)->execute(new AdvanceDispatchStageDTO(
            incidentDispatchId: $current->id,
            targetStage: $target,
            operatorUserId: $operatorUserId,
        ));
    }

    return $current;
}

beforeEach(function (): void {
    $this->seed(OperationalDemoSeeder::class);
});

test('incident accepts multiple vehicle dispatches', function (): void {
    /** @var User $user */
    $user = User::query()->where('email', 'municipal@example.com')->firstOrFail();

    /** @var Shift $shiftA */
    $shiftA = Shift::query()->where('municipio_id', $user->municipio_id)->firstOrFail();
    $shiftA->update(['status' => ShiftStatus::Disponivel, 'status_legacy' => 1]);

    /** @var Shift $shiftB */
    $shiftB = createDemoShift((int) $user->municipio_id, 'US 02', 'DEF2G34');
    $shiftB->update(['status' => ShiftStatus::Disponivel, 'status_legacy' => 1]);

    /** @var Incident $incident */
    $incident = Incident::query()->create([
        'municipio_id' => $user->municipio_id,
        'dispatch_year' => (int) now()->format('Y'),
        'talao' => 990001,
        'status' => IncidentStatus::Open,
        'occurred_at' => now(),
        'patient_call_type' => 'N',
    ]);

    app(DispatchUnitAction::class)->execute(new DispatchUnitDTO(
        incidentId: $incident->id,
        vehicleId: (int) $shiftA->vehicle_id,
        note: null,
        operatorUserId: $user->id,
    ));

    app(DispatchUnitAction::class)->execute(new DispatchUnitDTO(
        incidentId: $incident->id,
        vehicleId: (int) $shiftB->vehicle_id,
        note: null,
        operatorUserId: $user->id,
    ));

    $incident->refresh();

    expect(IncidentDispatch::query()->where('incident_id', $incident->id)->whereNull('deleted_at')->count())->toBe(2)
        ->and($incident->status)->toBe(IncidentStatus::Dispatched);
});

test('same shift can be dispatched to two incidents', function (): void {
    /** @var User $user */
    $user = User::query()->where('email', 'municipal@example.com')->firstOrFail();

    /** @var Shift $shift */
    $shift = Shift::query()->where('municipio_id', $user->municipio_id)->firstOrFail();
    $shift->update(['status' => ShiftStatus::Disponivel, 'status_legacy' => 1]);

    $incidentA = Incident::query()->create([
        'municipio_id' => $user->municipio_id,
        'dispatch_year' => (int) now()->format('Y'),
        'talao' => 990002,
        'status' => IncidentStatus::Open,
        'occurred_at' => now(),
        'patient_call_type' => 'N',
    ]);

    $incidentB = Incident::query()->create([
        'municipio_id' => $user->municipio_id,
        'dispatch_year' => (int) now()->format('Y'),
        'talao' => 990003,
        'status' => IncidentStatus::Open,
        'occurred_at' => now()->subMinute(),
        'patient_call_type' => 'N',
    ]);

    app(DispatchUnitAction::class)->execute(new DispatchUnitDTO(
        incidentId: $incidentA->id,
        vehicleId: (int) $shift->vehicle_id,
        note: null,
        operatorUserId: $user->id,
    ));

    app(DispatchUnitAction::class)->execute(new DispatchUnitDTO(
        incidentId: $incidentB->id,
        vehicleId: (int) $shift->vehicle_id,
        note: null,
        operatorUserId: $user->id,
    ));

    expect(IncidentDispatch::query()->where('shift_id', $shift->id)->whereNull('deleted_at')->count())->toBe(2)
        ->and($shift->fresh()->status)->toBe(ShiftStatus::Empenhado);
});

test('partial release keeps incident in progress', function (): void {
    /** @var User $user */
    $user = User::query()->where('email', 'municipal@example.com')->firstOrFail();

    /** @var Shift $shiftA */
    $shiftA = Shift::query()->where('municipio_id', $user->municipio_id)->firstOrFail();
    /** @var Shift $shiftB */
    $shiftB = createDemoShift((int) $user->municipio_id, 'US 03', 'GHI3J56');

    $incident = Incident::query()->create([
        'municipio_id' => $user->municipio_id,
        'dispatch_year' => (int) now()->format('Y'),
        'talao' => 990004,
        'status' => IncidentStatus::Open,
        'occurred_at' => now(),
        'patient_call_type' => 'N',
    ]);

    app(DispatchUnitAction::class)->execute(new DispatchUnitDTO($incident->id, (int) $shiftA->vehicle_id, null, $user->id));
    app(DispatchUnitAction::class)->execute(new DispatchUnitDTO($incident->id, (int) $shiftB->vehicle_id, null, $user->id));

    $dispatchA = IncidentDispatch::query()->where('incident_id', $incident->id)->where('shift_id', $shiftA->id)->firstOrFail();
    $dispatchB = IncidentDispatch::query()->where('incident_id', $incident->id)->where('shift_id', $shiftB->id)->firstOrFail();

    $stages = [
        DispatchStage::DepartedBase,
        DispatchStage::ArrivedScene,
        DispatchStage::LeftScene,
        DispatchStage::ArrivedHospital,
        DispatchStage::ReleasedHospital,
    ];

    advanceDispatchThroughStages($dispatchA, $stages, $user->id);
    advanceDispatchThroughStages($dispatchB, $stages, $user->id);

    $result = app(ReleaseUnitAction::class)->execute(new ReleaseUnitDTO(
        incidentId: $incident->id,
        vehicleId: (int) $shiftA->vehicle_id,
        operatorUserId: $user->id,
    ));

    $incident->refresh();

    expect($result->requiresIncidentClosure)->toBeFalse()
        ->and($incident->status)->toBe(IncidentStatus::InProgress)
        ->and(IncidentDispatch::query()->where('incident_id', $incident->id)->whereNull('deleted_at')->count())->toBe(1);
});

test('last release requires incident closure with primary vehicle', function (): void {
    /** @var User $user */
    $user = User::query()->where('email', 'municipal@example.com')->firstOrFail();

    /** @var Shift $shift */
    $shift = Shift::query()->where('municipio_id', $user->municipio_id)->firstOrFail();

    $incident = Incident::query()->create([
        'municipio_id' => $user->municipio_id,
        'dispatch_year' => (int) now()->format('Y'),
        'talao' => 990005,
        'status' => IncidentStatus::Open,
        'occurred_at' => now(),
        'patient_call_type' => 'N',
    ]);

    app(DispatchUnitAction::class)->execute(new DispatchUnitDTO($incident->id, (int) $shift->vehicle_id, null, $user->id));

    $dispatch = IncidentDispatch::query()->where('incident_id', $incident->id)->firstOrFail();

    advanceDispatchThroughStages($dispatch, [
        DispatchStage::DepartedBase,
        DispatchStage::ArrivedScene,
        DispatchStage::LeftScene,
        DispatchStage::ArrivedHospital,
        DispatchStage::ReleasedHospital,
    ], $user->id);

    $result = app(ReleaseUnitAction::class)->execute(new ReleaseUnitDTO(
        incidentId: $incident->id,
        vehicleId: (int) $shift->vehicle_id,
        operatorUserId: $user->id,
    ));

    expect($result->requiresIncidentClosure)->toBeTrue();

    app(FinalizeIncidentClosureAction::class)->execute(new FinalizeIncidentClosureDTO(
        incidentId: $incident->id,
        primaryShiftId: $shift->id,
        operatorUserId: $user->id,
    ));

    $incident->refresh();

    expect($incident->status)->toBe(IncidentStatus::PendingNurseReport)
        ->and($incident->primary_shift_id)->toBe($shift->id)
        ->and(
            IncidentDispatch::withTrashed()
                ->where('incident_id', $incident->id)
                ->where('shift_id', $shift->id)
                ->value('is_primary'),
        )->toBeTrue();
});

test('qta on one dispatch does not mark incident qta while another remains active', function (): void {
    /** @var User $user */
    $user = User::query()->where('email', 'municipal@example.com')->firstOrFail();

    /** @var Shift $shiftA */
    $shiftA = Shift::query()->where('municipio_id', $user->municipio_id)->firstOrFail();
    /** @var Shift $shiftB */
    $shiftB = createDemoShift((int) $user->municipio_id, 'US 04', 'JKL4M78');

    $incident = Incident::query()->create([
        'municipio_id' => $user->municipio_id,
        'dispatch_year' => (int) now()->format('Y'),
        'talao' => 990006,
        'status' => IncidentStatus::Dispatched,
        'occurred_at' => now(),
        'patient_call_type' => 'N',
    ]);

    $dispatchA = IncidentDispatch::query()->create([
        'municipio_id' => $user->municipio_id,
        'incident_id' => $incident->id,
        'shift_id' => $shiftA->id,
        'stage' => DispatchStage::ArrivedScene,
        'dispatched_at' => now(),
    ]);

    IncidentDispatch::query()->create([
        'municipio_id' => $user->municipio_id,
        'incident_id' => $incident->id,
        'shift_id' => $shiftB->id,
        'stage' => DispatchStage::ArrivedScene,
        'dispatched_at' => now(),
    ]);

    app(CancelDispatchAtSceneAction::class)->execute(
        $dispatchA,
        DispatchSceneCancelReason::NothingFound,
        $user->id,
    );

    $incident->refresh();

    expect($incident->status)->not->toBe(IncidentStatus::Qta)
        ->and(IncidentDispatch::query()->where('incident_id', $incident->id)->whereNull('deleted_at')->count())->toBe(1);
});

test('advance dispatch stage sets incident to in progress when leaving base', function (): void {
    /** @var User $user */
    $user = User::query()->where('email', 'municipal@example.com')->firstOrFail();

    /** @var Shift $shift */
    $shift = Shift::query()->where('municipio_id', $user->municipio_id)->firstOrFail();

    $incident = Incident::query()->create([
        'municipio_id' => $user->municipio_id,
        'dispatch_year' => (int) now()->format('Y'),
        'talao' => 990007,
        'status' => IncidentStatus::Open,
        'occurred_at' => now(),
        'patient_call_type' => 'N',
    ]);

    app(DispatchUnitAction::class)->execute(new DispatchUnitDTO($incident->id, (int) $shift->vehicle_id, null, $user->id));

    $dispatch = IncidentDispatch::query()->where('incident_id', $incident->id)->firstOrFail();

    app(AdvanceDispatchStageAction::class)->execute(new AdvanceDispatchStageDTO(
        incidentDispatchId: $dispatch->id,
        targetStage: DispatchStage::DepartedBase,
        operatorUserId: $user->id,
    ));

    $incident->refresh();
    $dispatch->refresh();

    expect($incident->status)->toBe(IncidentStatus::InProgress)
        ->and($dispatch->departed_base_at)->not->toBeNull();
});
