<?php

declare(strict_types=1);

use App\Integrations\Tracking\Providers\Ssx\Exceptions\SsxException;
use App\Integrations\Tracking\Providers\Ssx\SsxClient;
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
        'ssx.hash_central' => '',
        'ssx.hash_auth' => '',
        'ssx.client_integration_code_bus' => '',
        'ssx.timeout' => 5,
        'ssx.verify_ssl' => true,
        'ssx.token_cache_minutes' => 25,
    ]);
});

test('login envia credenciais na query e devolve o AccessToken', function (): void {
    Http::fake([
        'https://ssx.test/Login*' => Http::response(['AccessToken' => 'jwt-1', 'ExpiresIn' => 3600], 200),
    ]);

    $token = app(SsxClient::class)->login();

    expect($token)->toBe('jwt-1');

    Http::assertSent(function (Request $request): bool {
        return str_starts_with($request->url(), 'https://ssx.test/Login')
            && $request->method() === 'POST'
            && str_contains($request->url(), 'Username=reader')
            && str_contains($request->url(), 'Password=secret');
    });
});

test('token é cacheado e reutilizado entre chamadas', function (): void {
    Http::fake([
        'https://ssx.test/Login*' => Http::response(['AccessToken' => 'jwt-1', 'ExpiresIn' => 3600], 200),
        'https://ssx.test/v3/Tracking/PositionHistory/List' => Http::response([], 200),
    ]);

    $client = app(SsxClient::class);
    $client->positionHistory([['PropertyName' => 'EventDate', 'Condition' => '>=', 'Value' => '2026-06-24T00:00:00']]);
    $client->positionHistory([['PropertyName' => 'EventDate', 'Condition' => '>=', 'Value' => '2026-06-24T00:00:00']]);

    Http::assertSentCount(3); // 1 login + 2 consultas
    Http::assertSent(fn (Request $request): bool => str_starts_with($request->url(), 'https://ssx.test/Login'));
});

test('um 401 dispara novo login e repete a requisição', function (): void {
    Http::fake([
        'https://ssx.test/Login*' => Http::response(['AccessToken' => 'jwt-1', 'ExpiresIn' => 3600], 200),
        'https://ssx.test/v3/Tracking/PositionHistory/List' => Http::sequence()
            ->push(['error' => 'Token inválido'], 401)
            ->push([['TrackedUnitIntegrationCode' => '0001']], 200),
    ]);

    $rows = app(SsxClient::class)->positionHistory([
        ['PropertyName' => 'TrackedUnitIntegrationCode', 'Condition' => '=', 'Value' => '0001'],
    ]);

    expect($rows)->toHaveCount(1)
        ->and($rows[0]['TrackedUnitIntegrationCode'])->toBe('0001');

    Http::assertSentCount(4); // login + 401 + relogin + 200
});

test('204 sem conteúdo retorna lista vazia', function (): void {
    Http::fake([
        'https://ssx.test/Login*' => Http::response(['AccessToken' => 'jwt-1', 'ExpiresIn' => 3600], 200),
        'https://ssx.test/v3/Tracking/PositionHistory/List' => Http::response(null, 204),
    ]);

    expect(app(SsxClient::class)->positionHistory([
        ['PropertyName' => 'EventDate', 'Condition' => '>=', 'Value' => '2026-06-24T00:00:00'],
    ]))->toBe([]);
});

test('resolveTokenTtlSeconds interpreta ExpiresIn como ticks .NET, segundos ou fallback', function (): void {
    config(['ssx.token_cache_minutes' => 25]);

    $method = (new ReflectionClass(SsxClient::class))->getMethod('resolveTokenTtlSeconds');
    $client = app(SsxClient::class);

    // Instante absoluto em ticks .NET (~1 dia à frente) -> limitado ao teto de 12h.
    $ticks = (now()->addDay()->timestamp * 10_000_000) + 621355968000000000;
    expect($method->invoke($client, $ticks))->toBe(12 * 60 * 60);

    // Duração em segundos -> desconta a folga de 60s.
    expect($method->invoke($client, 3600))->toBe(3540);

    // Vazio/zero -> fallback de config (25 min).
    expect($method->invoke($client, 0))->toBe(25 * 60);
});

test('erro HTTP lança SsxException', function (): void {
    Http::fake([
        'https://ssx.test/Login*' => Http::response(['AccessToken' => 'jwt-1', 'ExpiresIn' => 3600], 200),
        'https://ssx.test/v3/Tracking/PositionHistory/List' => Http::response('boom', 500),
    ]);

    app(SsxClient::class)->positionHistory([
        ['PropertyName' => 'EventDate', 'Condition' => '>=', 'Value' => '2026-06-24T00:00:00'],
    ]);
})->throws(SsxException::class);
