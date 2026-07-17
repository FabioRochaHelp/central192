<?php

declare(strict_types=1);

namespace App\Integrations\Tracking\Providers\Ssx;

use Illuminate\Support\Facades\Cache;

/** Evita rajadas de requisições ao SSX após falhas consecutivas. */
final class SsxCircuitBreaker
{
    private const string FAILURES_KEY = 'ssx.circuit.failures';

    private const string OPEN_UNTIL_KEY = 'ssx.circuit.open_until';

    public static function isOpen(): bool
    {
        $openUntil = Cache::get(self::OPEN_UNTIL_KEY);

        // Só aceitamos timestamp Unix (int). Qualquer outro formato (ex.: valor
        // legado serializado como objeto) é tratado como expirado e descartado.
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
        $threshold = max(1, (int) config('ssx.circuit_breaker_threshold', 3));
        $cooldownMinutes = max(1, (int) config('ssx.circuit_breaker_cooldown_minutes', 5));

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
