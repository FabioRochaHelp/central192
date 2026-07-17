<?php

declare(strict_types=1);

use App\Integrations\Tracking\Contracts\TrackingProvider;
use App\Integrations\Tracking\Providers\SsxProvider;
use App\Integrations\Tracking\Providers\TraccarProvider;
use App\Models\Vehicle;
use Tests\TestCase;

uses(TestCase::class);

test('provider padrão resolve para Traccar', function (): void {
    config(['tracking.provider' => 'traccar']);

    expect(app(TrackingProvider::class))->toBeInstanceOf(TraccarProvider::class);
});

test('config ssx resolve para SsxProvider', function (): void {
    config(['tracking.provider' => 'ssx']);
    app()->forgetInstance(TrackingProvider::class);

    expect(app(TrackingProvider::class))->toBeInstanceOf(SsxProvider::class);
});

test('TraccarProvider mapeia device_id como unitReference', function (): void {
    $vehicle = new Vehicle(['device_id' => '42']);

    expect(app(TraccarProvider::class)->unitReferenceFor($vehicle))->toBe('42');
});

test('SsxProvider mapeia ssx_integration_code como unitReference', function (): void {
    $vehicle = new Vehicle(['ssx_integration_code' => '0001']);

    expect(app(SsxProvider::class)->unitReferenceFor($vehicle))->toBe('0001');
});
