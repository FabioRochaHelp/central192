<?php

declare(strict_types=1);

use App\Integrations\Traccar\TraccarCircuitBreaker;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

uses(TestCase::class);

beforeEach(function (): void {
    Cache::flush();
    TraccarCircuitBreaker::reset();

    config([
        'traccar.circuit_breaker_threshold' => 3,
        'traccar.circuit_breaker_cooldown_minutes' => 5,
    ]);
});

test('abre circuit breaker apos falhas consecutivas', function (): void {
    expect(TraccarCircuitBreaker::isOpen())->toBeFalse();

    TraccarCircuitBreaker::recordFailure();
    TraccarCircuitBreaker::recordFailure();
    expect(TraccarCircuitBreaker::isOpen())->toBeFalse();

    TraccarCircuitBreaker::recordFailure();
    expect(TraccarCircuitBreaker::isOpen())->toBeTrue();
});

test('record success fecha circuit breaker', function (): void {
    TraccarCircuitBreaker::recordFailure();
    TraccarCircuitBreaker::recordFailure();
    TraccarCircuitBreaker::recordFailure();

    expect(TraccarCircuitBreaker::isOpen())->toBeTrue();

    TraccarCircuitBreaker::recordSuccess();

    expect(TraccarCircuitBreaker::isOpen())->toBeFalse();
});
