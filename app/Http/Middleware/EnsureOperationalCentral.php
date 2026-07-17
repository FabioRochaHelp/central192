<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Somente operador central (`users_type_legacy` ≤ 2) — cadastros globais de parâmetros da ocorrência. */
final class EnsureOperationalCentral
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        abort_unless($user !== null && $user->isOperationalCentral(), 403);

        return $next($request);
    }
}
