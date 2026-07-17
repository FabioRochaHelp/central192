<?php

declare(strict_types=1);

namespace App\Domain\Operations\Services;

use App\Models\Incident;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Talão único por ano em todo o sistema (`unique(dispatch_year, talao)` em `incidents`).
 */
final class TalaoIssuer
{
    public function next(CarbonInterface $occurredAt): int
    {
        $year = (int) $occurredAt->format('Y');
        $lockKey = "talao:global:{$year}";

        return (int) Cache::lock($lockKey, 10)->block(5, function () use ($year): int {
            // Inclui soft-deletes: o índice único no banco cobre todas as linhas.
            $max = Incident::withoutGlobalScopes()
                ->where('dispatch_year', $year)
                ->max('talao');

            return $max ? ((int) $max) + 1 : 1;
        });
    }

    /** Para migração sem Redis/cache lock (SQLite dev). */
    public function nextWithinTransaction(CarbonInterface $occurredAt): int
    {
        $year = (int) $occurredAt->format('Y');

        $max = DB::table('incidents')
            ->where('dispatch_year', $year)
            ->max('talao');

        return $max ? ((int) $max) + 1 : 1;
    }
}
