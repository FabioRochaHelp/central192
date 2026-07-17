<?php

declare(strict_types=1);

namespace App\Actions\Fortify;

use App\Integrations\Cloudflare\TurnstileVerifier;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class ValidateTurnstile
{
    public function __construct(private TurnstileVerifier $turnstile) {}

    /**
     * @throws ValidationException
     */
    public function handle(Request $request, callable $next): mixed
    {
        if (! config('services.turnstile.enabled', true)) {
            return $next($request);
        }

        $token = $request->input('cf-turnstile-response');

        if (! $this->turnstile->verify($token, $request->ip())) {
            throw ValidationException::withMessages([
                'cf-turnstile-response' => [__('A verificação de segurança falhou. Tente novamente.')],
            ]);
        }

        return $next($request);
    }
}
