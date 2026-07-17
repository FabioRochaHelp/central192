<?php

declare(strict_types=1);

use App\Domain\Operations\Enums\DispatchStage;
use App\Domain\Operations\Enums\IncidentStatus;
use App\Models\Incident;
use App\Models\IncidentDispatch;
use App\Models\Shift;
use App\Models\Vehicle;
use App\Support\Operations\IncidentRouteContext;
use Tests\TestCase;

uses(TestCase::class);

test('pick route dispatch prefers primary dispatch even when soft deleted', function (): void {
    $vehicle = new Vehicle(['device_id' => '1001', 'prefix' => 'US 01']);
    $vehicle->id = 10;

    $shift = new Shift;
    $shift->id = 20;
    $shift->setRelation('vehicle', $vehicle);

    $historical = new IncidentDispatch([
        'shift_id' => 20,
        'is_primary' => true,
        'dispatched_at' => now()->subHours(2),
        'returned_base_at' => now()->subHour(),
        'stage' => DispatchStage::Dispatched,
    ]);
    $historical->id = 30;
    $historical->deleted_at = now()->subMinutes(30);
    $historical->setRelation('shift', $shift);

    $incident = new Incident([
        'status' => IncidentStatus::PendingNurseReport,
        'primary_shift_id' => 20,
    ]);
    $incident->id = 40;

    $picked = IncidentRouteContext::pickRouteDispatch($incident, collect([$historical]));

    expect($picked?->id)->toBe(30)
        ->and($picked?->shift?->vehicle?->id)->toBe(10);
});

test('pick route dispatch falls back to latest historical dispatch when none are active', function (): void {
    $vehicle = new Vehicle(['device_id' => '2002']);
    $vehicle->id = 1;

    $shift = new Shift;
    $shift->id = 2;
    $shift->setRelation('vehicle', $vehicle);

    $older = new IncidentDispatch(['shift_id' => 2, 'dispatched_at' => now()->subHours(3)]);
    $older->id = 10;
    $older->deleted_at = now()->subHours(2);
    $older->setRelation('shift', $shift);

    $newer = new IncidentDispatch(['shift_id' => 2, 'dispatched_at' => now()->subHours(2)]);
    $newer->id = 11;
    $newer->deleted_at = now()->subHour();
    $newer->setRelation('shift', $shift);

    $incident = new Incident(['status' => IncidentStatus::Closed]);
    $incident->id = 3;

    $picked = IncidentRouteContext::pickRouteDispatch($incident, collect([$older, $newer]));

    expect($picked?->id)->toBe(11);
});
