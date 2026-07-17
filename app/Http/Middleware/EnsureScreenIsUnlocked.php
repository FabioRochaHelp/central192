<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Support\Auth\ScreenLock;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureScreenIsUnlocked
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null) {
            return $next($request);
        }

        if ($request->is('broadcasting/*')) {
            return $next($request);
        }

        $screenLock = app(ScreenLock::class);

        if ($screenLock->isLocked($request->session())) {
            if ($request->routeIs('screen-lock.show', 'screen-lock.unlock', 'logout')) {
                return $next($request);
            }

            return redirect()->route('screen-lock.show');
        }

        if ($request->routeIs('screen-lock.show')) {
            return redirect()->route('dashboard');
        }

        return $next($request);
    }
}
