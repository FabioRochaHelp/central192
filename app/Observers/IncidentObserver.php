<?php

declare(strict_types=1);

namespace App\Observers;

use App\Domain\Operations\Events\DashboardCallStatsInvalidate;
use App\Models\Incident;

final class IncidentObserver
{
    public function updated(Incident $incident): void
    {
        if ($incident->wasChanged(['patient_call_type', 'occurred_at'])) {
            DashboardCallStatsInvalidate::dispatch();
        }
    }

    public function deleted(Incident $incident): void
    {
        unset($incident);
        DashboardCallStatsInvalidate::dispatch();
    }

    public function restored(Incident $incident): void
    {
        unset($incident);
        DashboardCallStatsInvalidate::dispatch();
    }
}
