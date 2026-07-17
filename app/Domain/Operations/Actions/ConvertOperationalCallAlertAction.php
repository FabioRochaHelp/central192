<?php

declare(strict_types=1);

namespace App\Domain\Operations\Actions;

use App\Domain\Operations\Enums\OperationalCallAlertStatus;
use App\Domain\Operations\Events\OperationalCallAlertClusterUpdated;
use App\Models\Incident;
use App\Models\OperationalCallAlert;
use App\Support\Operations\OperationalCallAlertGrouper;

/** Marca todos os alertas do ponto como convertidos e remove o cluster do mapa tático. */
final class ConvertOperationalCallAlertAction
{
    public function execute(string $alertId, Incident $incident): void
    {
        $alert = OperationalCallAlert::query()->find($alertId);
        if ($alert === null) {
            return;
        }

        $lat = (float) $alert->latitude;
        $lng = (float) $alert->longitude;

        foreach (OperationalCallAlertGrouper::activeAtLocation($lat, $lng) as $activeAlert) {
            if (! $activeAlert->isActionable()) {
                continue;
            }

            $activeAlert->update([
                'status' => OperationalCallAlertStatus::Converted,
                'converted_incident_id' => $incident->id,
            ]);
        }

        OperationalCallAlertClusterUpdated::dispatchForLocation($lat, $lng);
    }

    public function executeForLocation(string $locationKey, Incident $incident): void
    {
        $alerts = OperationalCallAlertGrouper::activeAtLocationKey($locationKey);
        if ($alerts->isEmpty()) {
            return;
        }

        $first = $alerts->first();
        $lat = (float) $first->latitude;
        $lng = (float) $first->longitude;

        foreach ($alerts as $activeAlert) {
            if (! $activeAlert->isActionable()) {
                continue;
            }

            $activeAlert->update([
                'status' => OperationalCallAlertStatus::Converted,
                'converted_incident_id' => $incident->id,
            ]);
        }

        OperationalCallAlertClusterUpdated::dispatchForLocation($lat, $lng);
    }
}
