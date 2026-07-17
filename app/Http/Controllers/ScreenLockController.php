<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\UnlockScreenRequest;
use App\Support\Auth\ScreenLock;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class ScreenLockController extends Controller
{
    public function show(Request $request, ScreenLock $screenLock): View|RedirectResponse
    {
        if (! $screenLock->isLocked($request->session())) {
            return redirect()->route('dashboard');
        }

        return view('pages.screen-lock');
    }

    public function store(Request $request, ScreenLock $screenLock): JsonResponse|RedirectResponse
    {
        $returnUrl = $request->headers->get('referer') ?: $request->fullUrl();

        $screenLock->lock($request->session(), $returnUrl);

        if ($request->expectsJson()) {
            return response()->json([
                'redirect' => route('screen-lock.show'),
            ]);
        }

        return redirect()->route('screen-lock.show');
    }

    public function unlock(UnlockScreenRequest $request, ScreenLock $screenLock): RedirectResponse
    {
        $user = $request->user();

        if ($user === null || ! $screenLock->verify($user, $request->string('password')->toString())) {
            throw ValidationException::withMessages([
                'password' => __('Senha incorreta.'),
            ]);
        }

        $returnUrl = $screenLock->unlock($request->session());

        return redirect()->to($returnUrl ?? route('dashboard'));
    }
}
