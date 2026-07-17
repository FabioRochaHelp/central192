<?php

declare(strict_types=1);

use App\Domain\Operations\Enums\CallType;
use App\Domain\Operations\Enums\ManchesterRisk;
use App\Models\Incident;
use App\Support\Operations\DispatchQueueIncidentSorter;
use Carbon\CarbonImmutable;
use Tests\TestCase;

uses(TestCase::class);

test('dispatch queue sorter places urgent before alert and normal', function (): void {
    $urgent = new Incident([
        'patient_call_type' => CallType::Urgent->value,
        'occurred_at' => CarbonImmutable::parse('2026-06-07 12:00:00'),
    ]);
    $urgent->id = 1;

    $alert = new Incident([
        'patient_call_type' => CallType::Alert->value,
        'occurred_at' => CarbonImmutable::parse('2026-06-07 11:30:00'),
    ]);
    $alert->id = 2;

    $normal = new Incident([
        'patient_call_type' => CallType::Normal->value,
        'occurred_at' => CarbonImmutable::parse('2026-06-07 11:00:00'),
    ]);
    $normal->id = 3;

    $sorted = DispatchQueueIncidentSorter::sort(collect([$normal, $alert, $urgent]));

    expect($sorted->pluck('id')->all())->toBe([1, 2, 3]);
});

test('dispatch queue sorter uses manchester risk within same call type', function (): void {
    $red = new Incident([
        'patient_call_type' => CallType::Normal->value,
        'manchester_risk' => ManchesterRisk::Red,
        'occurred_at' => CarbonImmutable::parse('2026-06-07 12:00:00'),
    ]);
    $red->id = 10;

    $blue = new Incident([
        'patient_call_type' => CallType::Normal->value,
        'manchester_risk' => ManchesterRisk::Blue,
        'occurred_at' => CarbonImmutable::parse('2026-06-07 11:00:00'),
    ]);
    $blue->id = 11;

    $sorted = DispatchQueueIncidentSorter::sort(collect([$blue, $red]));

    expect($sorted->pluck('id')->all())->toBe([10, 11]);
});
