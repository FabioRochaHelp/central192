<?php

declare(strict_types=1);

use App\Integrations\Traccar\Exceptions\TraccarException;
use App\Integrations\Traccar\TraccarClient;
use Illuminate\Http\Client\Request;
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
        'traccar.api_key' => 'token-123',
        'traccar.timeout' => 5,
        'traccar.verify_ssl' => true,
        'traccar.session_email' => 'session@example.com',
        'traccar.session_password' => 'session-secret',
        'traccar.session_cache_minutes' => 25,
    ]);
});

test('serverInfo consulta endpoint sem autenticacao', function (): void {
    Http::fake([
        'https://traccar.test/api/server' => Http::response(['id' => 1, 'version' => '6.0'], 200),
    ]);

    $info = app(TraccarClient::class)->serverInfo();

    expect($info)->toBe(['id' => 1, 'version' => '6.0']);

    Http::assertSent(function (Request $request): bool {
        return $request->url() === 'https://traccar.test/api/server'
            && $request->header('Authorization') === [];
    });
});

test('devices usa basic auth', function (): void {
    Http::fake([
        'https://traccar.test/api/devices' => Http::response([
            ['id' => 10, 'name' => 'VTR-01', 'uniqueId' => 'abc', 'status' => 'online'],
        ], 200),
    ]);

    $devices = app(TraccarClient::class)->devices();

    expect($devices)->toHaveCount(1)
        ->and($devices[0]['id'])->toBe(10);

    Http::assertSent(function (Request $request): bool {
        return $request->url() === 'https://traccar.test/api/devices'
            && $request->hasHeader('Authorization', 'Basic '.base64_encode('reader:secret'));
    });
});

test('devices usa bearer auth quando configurado', function (): void {
    config(['traccar.auth_type' => 'bearer']);

    Http::fake([
        'https://traccar.test/api/devices' => Http::response([['id' => 1]], 200),
    ]);

    app(TraccarClient::class)->devices();

    Http::assertSent(function (Request $request): bool {
        return $request->hasHeader('Authorization', 'Bearer token-123');
    });
});

test('devices faz fallback de sessao apos 401 no basic auth', function (): void {
    Http::fake([
        'https://traccar.test/api/devices' => Http::sequence()
            ->push([], 401)
            ->push([['id' => 99, 'name' => 'Fallback', 'uniqueId' => 'x', 'status' => 'online']], 200),
        'https://traccar.test/api/session' => Http::response('', 200, [
            'Set-Cookie' => 'JSESSIONID=abc123; Path=/; HttpOnly',
        ]),
    ]);

    $devices = app(TraccarClient::class)->devices();

    expect($devices)->toHaveCount(1)
        ->and($devices[0]['id'])->toBe(99);

    Http::assertSent(function (Request $request): bool {
        return $request->url() === 'https://traccar.test/api/session'
            && $request['email'] === 'session@example.com'
            && $request['password'] === 'session-secret';
    });

    Http::assertSent(function (Request $request): bool {
        return $request->url() === 'https://traccar.test/api/devices'
            && $request->hasHeader('Cookie', 'JSESSIONID=abc123');
    });
});

test('session auth reutiliza cookie em cache', function (): void {
    config(['traccar.auth_type' => 'session']);

    Http::fake([
        'https://traccar.test/api/session' => Http::response('', 200, [
            'Set-Cookie' => 'JSESSIONID=cached; Path=/',
        ]),
        'https://traccar.test/api/devices' => Http::response([['id' => 1]], 200),
        'https://traccar.test/api/positions' => Http::response([], 200),
    ]);

    $client = app(TraccarClient::class);
    $client->devices();
    $client->positions();

    Http::assertSentCount(3);
    Http::assertSent(fn (Request $request): bool => $request->url() === 'https://traccar.test/api/session');
});

test('normalize retorna status body e raw', function (): void {
    Http::fake([
        'https://traccar.test/api/server' => Http::response(['ok' => true], 200),
    ]);

    $client = app(TraccarClient::class);
    $client->serverInfo();

    $response = Http::recorded()[0][1];
    $normalized = $client->normalize($response);

    expect($normalized['status'])->toBe(200)
        ->and($normalized['body'])->toBe(['ok' => true])
        ->and($normalized['raw'])->toContain('ok');
});

test('rejeita base_url http em producao', function (): void {
    $previous = app()->environment();

    try {
        app()->detectEnvironment(fn (): string => 'production');
        config(['traccar.base_url' => 'http://traccar.test']);

        expect(fn () => app(TraccarClient::class)->serverInfo())
            ->toThrow(RuntimeException::class, 'HTTPS');
    } finally {
        app()->detectEnvironment(fn () => $previous);
    }
});

test('devices lanca TraccarException em erro http', function (): void {
    Http::fake([
        'https://traccar.test/api/devices' => Http::response([], 503),
    ]);

    expect(fn () => app(TraccarClient::class)->devices())
        ->toThrow(TraccarException::class);
});

test('positions envia deviceId quando informado', function (): void {
    Http::fake([
        'https://traccar.test/api/positions*' => Http::response([], 200),
    ]);

    app(TraccarClient::class)->positions(42);

    Http::assertSent(function (Request $request): bool {
        return str_contains($request->url(), 'deviceId=42');
    });
});
