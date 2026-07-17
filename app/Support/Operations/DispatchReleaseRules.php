<?php

declare(strict_types=1);

namespace App\Support\Operations;

use App\Domain\Operations\Enums\DispatchStage;
use App\Domain\Operations\Enums\IncidentReportModality;
use App\Models\Incident;
use App\Models\IncidentDispatch;

final class DispatchReleaseRules
{
    /** Etapa em que a viatura pode ser liberada conforme a modalidade da natureza. */
    public static function requiredReleaseStage(Incident $incident): DispatchStage
    {
        $modality = $incident->nature?->report_modality;

        if ($modality instanceof IncidentReportModality && $modality->closesAtLeftScene()) {
            return DispatchStage::LeftScene;
        }

        return DispatchStage::ReleasedHospital;
    }

    public static function canReleaseVehicle(IncidentDispatch $dispatch, Incident $incident): bool
    {
        if ($dispatch->deleted_at !== null) {
            return false;
        }

        if ($dispatch->shift?->vehicle_id === null) {
            return false;
        }

        if ($dispatch->stage === DispatchStage::ReleasedHospital) {
            return true;
        }

        return $dispatch->stage === self::requiredReleaseStage($incident);
    }
}
