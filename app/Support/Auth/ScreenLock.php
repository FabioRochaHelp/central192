<?php

declare(strict_types=1);

namespace App\Support\Auth;

use App\Models\User;
use Illuminate\Contracts\Session\Session;
use Illuminate\Support\Facades\Hash;

final class ScreenLock
{
    public const string SESSION_KEY = 'screen_locked';

    public const string RETURN_URL_KEY = 'screen_lock_return_url';

    public function isLocked(Session $session): bool
    {
        return (bool) $session->get(self::SESSION_KEY, false);
    }

    public function lock(Session $session, ?string $returnUrl = null): void
    {
        $session->put(self::SESSION_KEY, true);
        $session->put(self::RETURN_URL_KEY, $returnUrl);
    }

    /**
     * @return string|null The URL the user should return to after unlocking.
     */
    public function unlock(Session $session): ?string
    {
        $returnUrl = $session->get(self::RETURN_URL_KEY);

        $session->forget([
            self::SESSION_KEY,
            self::RETURN_URL_KEY,
        ]);

        return is_string($returnUrl) && $returnUrl !== '' ? $returnUrl : null;
    }

    public function verify(User $user, string $password): bool
    {
        return Hash::check($password, $user->password);
    }
}
