<?php

declare(strict_types=1);

use App\Integrations\Cloudflare\TurnstileVerifier;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

uses(TestCase::class);

beforeEach(function (): void {
    config([
        'services.turnstile.enabled' => true,
        'services.turnstile.secret_key' => 'test-secret-key',
    ]);
});

test('verify returns true when turnstile is disabled', function (): void {
    config(['services.turnstile.enabled' => false]);

    $result = app(TurnstileVerifier::class)->verify(null, '127.0.0.1');

    expect($result)->toBeTrue();
    Http::assertNothingSent();
});

test('verify returns false when token is missing', function (): void {
    $result = app(TurnstileVerifier::class)->verify(null, '127.0.0.1');

    expect($result)->toBeFalse();
    Http::assertNothingSent();
});

test('verify returns false when secret key is missing', function (): void {
    config(['services.turnstile.secret_key' => null]);

    $result = app(TurnstileVerifier::class)->verify('token-123', '127.0.0.1');

    expect($result)->toBeFalse();
    Http::assertNothingSent();
});

test('verify calls siteverify and returns true on success', function (): void {
    Http::fake([
        'challenges.cloudflare.com/turnstile/v0/siteverify' => Http::response(['success' => true], 200),
    ]);

    $result = app(TurnstileVerifier::class)->verify('token-123', '203.0.113.1');

    expect($result)->toBeTrue();

    Http::assertSent(function (Request $request): bool {
        return $request->url() === 'https://challenges.cloudflare.com/turnstile/v0/siteverify'
            && $request['secret'] === 'test-secret-key'
            && $request['response'] === 'token-123'
            && $request['remoteip'] === '203.0.113.1';
    });
});

test('verify returns false when siteverify reports failure', function (): void {
    Http::fake([
        'challenges.cloudflare.com/turnstile/v0/siteverify' => Http::response([
            'success' => false,
            'error-codes' => ['invalid-input-response'],
        ], 200),
    ]);

    $result = app(TurnstileVerifier::class)->verify('bad-token', '127.0.0.1');

    expect($result)->toBeFalse();
});

test('verify returns false when siteverify request fails', function (): void {
    Http::fake([
        'challenges.cloudflare.com/turnstile/v0/siteverify' => Http::response([], 500),
    ]);

    $result = app(TurnstileVerifier::class)->verify('token-123', '127.0.0.1');

    expect($result)->toBeFalse();
});
