<?php

declare(strict_types=1);

use App\Integrations\Wind\DTOs\WindReading;
use App\Integrations\Wind\WindService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

uses(TestCase::class);

beforeEach(function (): void {
    Cache::flush();

    config([
        'wind.base_url' => 'https://wind.test',
        'wind.forecast_path' => 'v1/forecast',
        'wind.timeout' => 5,
        'wind.cache_ttl_minutes' => 15,
        'wind.grid.steps' => [0.25, 0.5, 1.0, 2.0],
        'wind.grid.max_cells' => 24,
    ]);
});

/** Responde qualquer quantidade de pontos, ecoando o que foi pedido. */
function fakeWindForRequestedPoints(float $speed = 10.0, float $direction = 90.0): void
{
    Http::fake(function ($request) use ($speed, $direction) {
        parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);
        $lats = explode(',', (string) $query['latitude']);

        return Http::response(array_map(static fn (string $lat): array => [
            'latitude' => (float) $lat,
            'longitude' => -51.0,
            'current' => [
                'time' => '2026-07-16T12:00',
                'wind_speed_10m' => $speed,
                'wind_direction_10m' => $direction,
            ],
        ], $lats));
    });
}

it('converte a direção meteorológica no sentido em que o fogo corre', function (): void {
    // Vento vindo de nordeste (43°) sopra para sudoeste (223°).
    expect((new WindReading(-22.0, -51.0, 17.8, 43.0, null, null))->spreadsToDeg())->toBe(223.0);
});

it('mantém o sentido dentro de 0–360 ao dar a volta', function (): void {
    expect((new WindReading(-22.0, -51.0, 10.0, 200.0, null, null))->spreadsToDeg())->toBe(20.0)
        ->and((new WindReading(-22.0, -51.0, 10.0, 180.0, null, null))->spreadsToDeg())->toBe(0.0)
        ->and((new WindReading(-22.0, -51.0, 10.0, 0.0, null, null))->spreadsToDeg())->toBe(180.0);
});

it('expõe as duas direções no payload do mapa, para a seta não sair invertida', function (): void {
    $payload = (new WindReading(-22.0, -51.0, 17.8, 43.0, 41.0, '2026-07-16T12:00'))->toLeaflet();

    expect($payload['direction_from_deg'])->toBe(43.0)
        ->and($payload['spreads_to_deg'])->toBe(223.0);
});

it('ancora as células em múltiplos do passo, e não nas bordas do viewport', function (): void {
    fakeWindForRequestedPoints();

    $readings = app(WindService::class)->forBounds(-22.1, -51.4, -22.0, -51.3);

    // floor(-22.1/0.25)*0.25 = -22.25 — âncora global, não o -22.1 pedido.
    expect(collect($readings)->pluck('latitude')->all())->toContain(-22.25);
});

it('afrouxa a resolução para caber no teto de células', function (): void {
    config(['wind.grid.max_cells' => 8]);
    fakeWindForRequestedPoints();

    // Área grande: no passo de 0.25° seriam centenas de pontos.
    $readings = app(WindService::class)->forBounds(-25.5, -53.2, -19.7, -44.0);

    expect(count($readings))->toBeLessThanOrEqual(8)
        ->and($readings)->not->toBeEmpty();
});

it('respeita o teto mesmo quando nem o passo mais grosso cabe', function (): void {
    config(['wind.grid.max_cells' => 3, 'wind.grid.steps' => [0.25]]);
    fakeWindForRequestedPoints();

    expect(count(app(WindService::class)->forBounds(-25.5, -53.2, -19.7, -44.0)))->toBe(3);
});

it('reaproveita o cache entre chamadas em vez de repetir a requisição', function (): void {
    fakeWindForRequestedPoints();

    $service = app(WindService::class);
    $first = $service->forBounds(-22.1, -51.4, -22.0, -51.3);
    $second = $service->forBounds(-22.1, -51.4, -22.0, -51.3);

    expect($second)->toEqual($first);

    Http::assertSentCount(1);
});

it('devolve o que houver em cache quando o provedor cai, sem derrubar o mapa', function (): void {
    fakeWindForRequestedPoints();
    $service = app(WindService::class);
    $cached = $service->forBounds(-22.1, -51.4, -22.0, -51.3);
    expect($cached)->not->toBeEmpty();

    Http::fake(['wind.test/*' => Http::response('down', 503)]);

    // Mesma área: tudo vem do cache, o provedor nem é consultado.
    expect($service->forBounds(-22.1, -51.4, -22.0, -51.3))->toEqual($cached);
});

it('degrada para lista vazia quando o provedor cai e não há cache', function (): void {
    Http::fake(['wind.test/*' => Http::response('down', 503)]);

    expect(app(WindService::class)->forBounds(-22.1, -51.4, -22.0, -51.3))->toBe([]);
});
