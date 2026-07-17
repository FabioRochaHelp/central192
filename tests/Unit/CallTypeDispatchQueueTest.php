<?php

declare(strict_types=1);

use App\Domain\Operations\Enums\CallType;

test('dispatch queue sort order follows U then L then N', function (): void {
    expect(CallType::Urgent->dispatchQueueSortOrder())->toBe(0)
        ->and(CallType::Alert->dispatchQueueSortOrder())->toBe(1)
        ->and(CallType::Normal->dispatchQueueSortOrder())->toBe(2)
        ->and(CallType::Administrative->dispatchQueueSortOrder())->toBe(3)
        ->and(CallType::Hoax->dispatchQueueSortOrder())->toBe(99);
});

test('dispatch queue accent classes define badge and row styles', function (): void {
    $urgent = CallType::Urgent->dispatchQueueAccentClasses();

    expect($urgent)->toHaveKeys(['row', 'badge', 'bar'])
        ->and($urgent['badge'])->toContain('bg-red-600');
});
