<?php

declare(strict_types=1);

namespace App\Domain\Operations\Actions;

use App\Domain\Operations\Enums\OperationalCallAlertStatus;
use App\Domain\Operations\Events\OperationalCallAlertClusterUpdated;
use App\Models\OperationalCallAlert;
use App\Models\User;

/** Coloca alerta (e localização) em modo monitoramento no mapa tático. */
final class MonitorOperationalCallAlertAction
{
    public function execute(OperationalCallAlert $alert, User $actor): void
    {
        if (! $alert->isActionable()) {
            return;
        }

        $alert->update([
            'status' => OperationalCallAlertStatus::Monitoring,
            'monitored_by' => $actor->id,
            'monitored_at' => now(),
        ]);

        OperationalCallAlertClusterUpdated::dispatchForLocation(
            (float) $alert->latitude,
            (float) $alert->longitude,
        );
    }
}
