<?php

declare(strict_types=1);

use App\Integrations\Tracking\DTOs\TrackingPosition;
use App\Integrations\Tracking\Providers\Ssx\SsxService;
use Carbon\Carbon;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

uses(TestCase::class);

beforeEach(function (): void {
    Cache::flush();

    config([
        'ssx.base_url' => 'https://ssx.test',
        'ssx.username' => 'reader',
        'ssx.password' => 'secret',
        'ssx.date_format' => 'Y-m-d\TH:i:s',
    ]);

    Http::fake([
        'https://ssx.test/Login*' => Http::response(['AccessToken' => 'jwt-1', 'ExpiresIn' => 3600], 200),
    ]);
});

test('positions usa GetLastPositions e mapeia a última posição de cada unidade', function (): void {
    Http::fake([
        'https://ssx.test/Login*' => Http::response(['AccessToken' => 'jwt-1', 'ExpiresIn' => 3600], 200),
        'https://ssx.test/Controlws/LastPosition/GetLastPositions' => Http::response([
            ['TrackedUnitIntegrationCode' => '0001', 'EventDate' => '2026-06-24T10:05:00', 'Latitude' => -1.5, 'Longitude' => -2.5, 'ValidGPS' => true, 'Ignition' => true, 'Address' => 'Rua A'],
            ['TrackedUnitIntegrationCode' => '0002', 'EventDate' => '2026-06-24T10:01:00', 'Latitude' => -3.0, 'Longitude' => -4.0, 'ValidGPS' => false],
            ['TrackedUnitIntegrationCode' => '', 'EventDate' => '2026-06-24T10:09:00', 'Latitude' => 0, 'Longitude' => 0],
        ], 200),
    ]);

    $positions = app(SsxService::class)->positions();

    expect($positions)->toHaveCount(2);

    $unit1 = $positions->firstWhere('unitReference', '0001');
    expect($unit1)->toBeInstanceOf(TrackingPosition::class)
        ->and($unit1->fixTime)->toBe('2026-06-24T10:05:00')
        ->and($unit1->latitude)->toBe(-1.5)
        ->and($unit1->ignition)->toBeTrue()
        ->and($unit1->address)->toBe('Rua A');
});

test('units lista unidades com nome para o seletor de cadastro', function (): void {
    Http::fake([
        'https://ssx.test/Login*' => Http::response(['AccessToken' => 'jwt-1', 'ExpiresIn' => 3600], 200),
        'https://ssx.test/Controlws/LastPosition/GetLastPositions' => Http::response([
            ['TrackedUnitIntegrationCode' => '0002', 'TrackedUnit' => 'US 02', 'EventDate' => '2026-06-24T10:01:00', 'ValidGPS' => false],
            ['TrackedUnitIntegrationCode' => '0001', 'TrackedUnit' => 'US 01', 'EventDate' => '2026-06-24T10:05:00', 'ValidGPS' => true],
            ['TrackedUnitIntegrationCode' => '0003', 'TrackedUnit' => '', 'EventDate' => '2026-06-24T10:02:00', 'ValidGPS' => true],
            ['TrackedUnitIntegrationCode' => '', 'TrackedUnit' => 'sem código', 'EventDate' => '2026-06-24T10:09:00'],
        ], 200),
    ]);

    $units = app(SsxService::class)->units();

    expect($units)->toHaveCount(3)
        ->and($units->firstWhere('code', '0001')['name'])->toBe('US 01')
        ->and($units->firstWhere('code', '0003')['name'])->toBe('0003') // sem TrackedUnit -> usa o código
        ->and($units->pluck('code')->all())->toBe(['0003', '0001', '0002']); // ordem natural por nome
});

test('positions envia corpo vazio (sem filtro) ao GetLastPositions', function (): void {
    Http::fake([
        'https://ssx.test/Login*' => Http::response(['AccessToken' => 'jwt-1', 'ExpiresIn' => 3600], 200),
        'https://ssx.test/Controlws/LastPosition/GetLastPositions' => Http::response([], 200),
    ]);

    app(SsxService::class)->positions();

    Http::assertSent(function (Request $request): bool {
        return str_contains($request->url(), 'Controlws/LastPosition/GetLastPositions')
            && $request->method() === 'POST'
            && $request->body() === '{}';
    });
});

test('route filtra por código e intervalo e ordena por fixTime', function (): void {
    Http::fake([
        'https://ssx.test/Login*' => Http::response(['AccessToken' => 'jwt-1', 'ExpiresIn' => 3600], 200),
        'https://ssx.test/v3/Tracking/PositionHistory/List' => Http::response([
            ['EventDate' => '2026-06-24T10:05:00', 'Latitude' => -1.5, 'Longitude' => -2.5],
            ['EventDate' => '2026-06-24T10:00:00', 'Latitude' => -1.0, 'Longitude' => -2.0],
        ], 200),
    ]);

    $points = app(SsxService::class)->route(
        '0001',
        Carbon::parse('2026-06-24T09:00:00'),
        Carbon::parse('2026-06-24T11:00:00'),
    );

    expect($points)->toHaveCount(2)
        ->and($points->first()->fixTime)->toBe('2026-06-24T10:00:00');

    Http::assertSent(function (Request $request): bool {
        if (! str_contains($request->url(), 'PositionHistory/List')) {
            return false;
        }

        $body = $request->data();

        return $body[0] === ['PropertyName' => 'TrackedUnitIntegrationCode', 'Condition' => '=', 'Value' => '0001'];
    });
});
