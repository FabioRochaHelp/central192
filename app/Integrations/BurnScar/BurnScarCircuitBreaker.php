<?php

declare(strict_types=1);

namespace App\Integrations\BurnScar;

use Illuminate\Support\Facades\Cache;

/** Evita rajadas de requisições ao serviço de cicatriz após falhas consecutivas. */
final class BurnScarCircuitBreaker
{
    private const string FAILURES_KEY = 'burnscar.circuit.failures';

    private const string OPEN_UNTIL_KEY = 'burnscar.circuit.open_until';

    public static function isOpen(): bool
    {
        $openUntil = Cache::get(self::OPEN_UNTIL_KEY);

        if (! is_numeric($openUntil)) {
            if ($openUntil !== null) {
                Cache::forget(self::OPEN_UNTIL_KEY);
                Cache::forget(self::FAILURES_KEY);
            }

            return false;
        }

        if (now()->getTimestamp() >= (int) $openUntil) {
            Cache::forget(self::OPEN_UNTIL_KEY);
            Cache::forget(self::FAILURES_KEY);

            return false;
        }

        return true;
    }

    public static function recordSuccess(): void
    {
        Cache::forget(self::FAILURES_KEY);
        Cache::forget(self::OPEN_UNTIL_KEY);
    }

    public static function recordFailure(): void
    {
        $threshold = max(1, (int) config('burnscar.circuit_breaker_threshold', 3));
        $cooldownMinutes = max(1, (int) config('burnscar.circuit_breaker_cooldown_minutes', 5));

        $failures = (int) Cache::get(self::FAILURES_KEY, 0) + 1;
        Cache::put(self::FAILURES_KEY, $failures, now()->addMinutes($cooldownMinutes));

        if ($failures >= $threshold) {
            $openUntil = now()->addMinutes($cooldownMinutes);
            Cache::put(self::OPEN_UNTIL_KEY, $openUntil->getTimestamp(), $openUntil);
        }
    }

    public static function reset(): void
    {
        Cache::forget(self::FAILURES_KEY);
        Cache::forget(self::OPEN_UNTIL_KEY);
    }
}
