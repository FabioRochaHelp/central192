<?php

declare(strict_types=1);

namespace App\Integrations\Cloudflare;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

final class TurnstileVerifier
{
    private const string VERIFY_URL = 'https://challenges.cloudflare.com/turnstile/v0/siteverify';

    public function verify(?string $token, ?string $remoteIp = null): bool
    {
        if (! config('services.turnstile.enabled', true)) {
            return true;
        }

        if (blank($token)) {
            return false;
        }

        $secretKey = config('services.turnstile.secret_key');

        if (blank($secretKey)) {
            Log::warning('Turnstile secret key is not configured.');

            return false;
        }

        try {
            $response = Http::asForm()
                ->timeout(10)
                ->post(self::VERIFY_URL, array_filter([
                    'secret' => $secretKey,
                    'response' => $token,
                    'remoteip' => $remoteIp,
                ]));
        } catch (ConnectionException $exception) {
            Log::warning('Turnstile verification request failed.', [
                'message' => $exception->getMessage(),
            ]);

            return false;
        }

        if (! $response->successful()) {
            return false;
        }

        return (bool) ($response->json('success') ?? false);
    }
}
