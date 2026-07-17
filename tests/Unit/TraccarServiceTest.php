<?php

declare(strict_types=1);

use App\Integrations\Traccar\DTOs\TraccarPosition;
use App\Integrations\Traccar\TraccarService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

uses(TestCase::class);

beforeEach(function (): void {
    Cache::flush();

    config([
        'traccar.base_url' => 'https://traccar.test',
        'traccar.auth_type' => 'basic',
        'traccar.username' => 'reader',
        'traccar.password' => 'secret',
        'traccar.timeout' => 5,
        'traccar.verify_ssl' => true,
    ]);
});

test('mapeia posicao do traccar para dto com velocidade em kmh', function (): void {
    Http::fake([
        'https://traccar.test/api/positions*' => Http::response([
            [
                'id' => 1,
                'deviceId' => 42,
                'fixTime' => '2026-06-10T12:00:00.000Z',
                'latitude' => -15.5,
                'longitude' => -47.5,
                'altitude' => 800,
                'speed' => 10,
                'course' => 90,
                'address' => 'Brasília',
                'valid' => true,
            ],
        ], 200),
    ]);

    $positions = app(TraccarService::class)->positions();

    expect($positions)->toHaveCount(1);

    /** @var TraccarPosition $position */
    $position = $positions->first();

    expect($position->deviceId)->toBe(42)
        ->and($position->speedKmh())->toBe(18.5);
});

test('ping retorna true quando server responde com id', function (): void {
    Http::fake([
        'https://traccar.test/api/server' => Http::response(['id' => 1], 200),
    ]);

    expect(app(TraccarService::class)->ping())->toBeTrue();
});

test('ping retorna false quando server falha', function (): void {
    Http::fake([
        'https://traccar.test/api/server' => Http::response([], 503),
    ]);

    expect(app(TraccarService::class)->ping())->toBeFalse();
});

test('route usa fallback de positions history quando reports route falha', function (): void {
    Http::fake([
        'https://traccar.test/api/reports/route*' => Http::response([], 400),
        'https://traccar.test/api/positions*' => Http::response([
            [
                'latitude' => -15.1,
                'longitude' => -47.1,
                'fixTime' => '2026-06-10T10:00:00.000Z',
                'speed' => 4,
            ],
        ], 200),
    ]);

    $points = app(TraccarService::class)->route(
        42,
        now()->subHour(),
        now(),
    );

    expect($points)->toHaveCount(1)
        ->and($points->first()?->latitude)->toBe(-15.1);
});

test('route envia deviceId como array no reports route', function (): void {
    Http::fake([
        'https://traccar.test/api/reports/route*' => Http::response([
            [
                'latitude' => -15.2,
                'longitude' => -47.2,
                'fixTime' => '2026-06-10T11:00:00.000Z',
                'speed' => 2,
            ],
        ], 200),
    ]);

    app(TraccarService::class)->route(42, now()->subHour(), now());

    Http::assertSent(function ($request): bool {
        return str_contains($request->url(), 'reports/route')
            && str_contains($request->url(), 'deviceId');
    });
});
