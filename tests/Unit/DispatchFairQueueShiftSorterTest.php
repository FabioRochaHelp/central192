<?php

declare(strict_types=1);

use App\Models\Shift;
use App\Support\Operations\DispatchFairQueueShiftSorter;
use Carbon\CarbonImmutable;
use Tests\TestCase;

uses(TestCase::class);

test('fair queue sorter places longest waiting shift at base first', function (): void {
    $older = Shift::make([
        'starts_at' => CarbonImmutable::parse('2026-06-07 08:00:00'),
        'available_at' => CarbonImmutable::parse('2026-06-07 08:00:00'),
    ]);
    $older->id = 1;

    $newer = Shift::make([
        'starts_at' => CarbonImmutable::parse('2026-06-07 10:00:00'),
        'available_at' => CarbonImmutable::parse('2026-06-07 10:00:00'),
    ]);
    $newer->id = 2;

    $sorted = DispatchFairQueueShiftSorter::sort(collect([$newer, $older]));

    expect($sorted->pluck('id')->all())->toBe([1, 2]);
});

test('fair queue sorter falls back to starts_at when available_at is missing', function (): void {
    $first = Shift::make(['starts_at' => CarbonImmutable::parse('2026-06-07 07:00:00')]);
    $first->id = 10;

    $second = Shift::make(['starts_at' => CarbonImmutable::parse('2026-06-07 09:00:00')]);
    $second->id = 11;

    $sorted = DispatchFairQueueShiftSorter::sort(collect([$second, $first]));

    expect($sorted->pluck('id')->all())->toBe([10, 11]);
});
