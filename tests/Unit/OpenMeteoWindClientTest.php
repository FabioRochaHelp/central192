<?php

declare(strict_types=1);

use App\Integrations\Wind\Exceptions\WindException;
use App\Integrations\Wind\OpenMeteoWindClient;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

uses(TestCase::class);

beforeEach(function (): void {
    config([
        'wind.base_url' => 'https://wind.test',
        'wind.forecast_path' => 'v1/forecast',
        'wind.timeout' => 5,
    ]);
});

function windPayload(float $lat, float $lng, float $speed, float $direction, ?float $gusts = null): array
{
    return [
        'latitude' => $lat,
        'longitude' => $lng,
        'current' => array_filter([
            'time' => '2026-07-16T12:00',
            'interval' => 900,
            'wind_speed_10m' => $speed,
            'wind_direction_10m' => $direction,
            'wind_gusts_10m' => $gusts,
        ], static fn ($value): bool => $value !== null),
    ];
}

it('normaliza a resposta de ponto único, que vem como objeto e não como lista', function (): void {
    Http::fake([
        'wind.test/*' => Http::response(windPayload(-22.108963, -51.40207, 17.8, 43.0, 41.0)),
    ]);

    $readings = app(OpenMeteoWindClient::class)->current([[-22.12, -51.39]]);

    expect($readings)->toHaveCount(1)
        ->and($readings[0]->speedKmh)->toBe(17.8)
        ->and($readings[0]->directionFromDeg)->toBe(43.0)
        ->and($readings[0]->gustsKmh)->toBe(41.0)
        ->and($readings[0]->observedAt)->toBe('2026-07-16T12:00');
});

it('mantém as coordenadas pedidas, não as ajustadas à grade do provedor', function (): void {
    Http::fake([
        // O provedor devolve o centro da célula do modelo, deslocado do que pedimos.
        'wind.test/*' => Http::response([windPayload(-22.108963, -51.40207, 10.0, 90.0)]),
    ]);

    $readings = app(OpenMeteoWindClient::class)->current([[-22.25, -51.5]]);

    expect($readings[0]->latitude)->toBe(-22.25)
        ->and($readings[0]->longitude)->toBe(-51.5);
});

it('pede todos os pontos numa única requisição e casa cada resultado pela ordem', function (): void {
    Http::fake([
        'wind.test/*' => Http::response([
            windPayload(-22.0, -51.0, 5.0, 0.0),
            windPayload(-23.0, -52.0, 25.0, 180.0),
        ]),
    ]);

    $readings = app(OpenMeteoWindClient::class)->current([[-22.0, -51.0], [-23.0, -52.0]]);

    expect($readings)->toHaveCount(2)
        ->and($readings[0]->speedKmh)->toBe(5.0)
        ->and($readings[1]->speedKmh)->toBe(25.0);

    Http::assertSentCount(1);

    Http::assertSent(function (Request $request): bool {
        return str_contains($request->url(), 'latitude=-22%2C-23')
            && str_contains($request->url(), 'longitude=-51%2C-52');
    });
});

it('não chama o provedor quando não há pontos', function (): void {
    Http::fake();

    expect(app(OpenMeteoWindClient::class)->current([]))->toBe([]);

    Http::assertNothingSent();
});

it('descarta resultados sem os campos de vento em vez de inventar zero', function (): void {
    Http::fake([
        'wind.test/*' => Http::response([
            ['latitude' => -22.0, 'longitude' => -51.0, 'current' => ['time' => '2026-07-16T12:00']],
        ]),
    ]);

    expect(app(OpenMeteoWindClient::class)->current([[-22.0, -51.0]]))->toBe([]);
});

it('lança WindException quando o provedor responde com erro', function (): void {
    Http::fake([
        'wind.test/*' => Http::response('upstream down', 503),
    ]);

    app(OpenMeteoWindClient::class)->current([[-22.0, -51.0]]);
})->throws(WindException::class);
