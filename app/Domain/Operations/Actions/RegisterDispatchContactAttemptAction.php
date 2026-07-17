<?php

declare(strict_types=1);

namespace App\Domain\Operations\Actions;

use App\Domain\Operations\DTOs\DispatchContactAttemptDTO;
use App\Domain\Operations\Services\IncidentTimelineRecorder;
use App\Models\Incident;

final class RegisterDispatchContactAttemptAction
{
    public function __construct(private IncidentTimelineRecorder $timeline) {}

    public function execute(DispatchContactAttemptDTO $dto): void
    {
        /** @var Incident $incident */
        $incident = Incident::query()->findOrFail($dto->incidentId);

        $eventKey = $dto->successful
            ? 'dispatch_contact_attempted'
            : 'dispatch_contact_failed';

        $payload = [
            'vehicle_id' => $dto->vehicleId,
            'contact_method' => $dto->contactMethod,
            'contact_details' => $dto->contactDetails,
            'successful' => $dto->successful,
            'reason' => $dto->reason,
            'nearest_vehicle' => $dto->nearestVehicle,
        ];

        $this->timeline->record($incident, $eventKey, $payload);
    }
}
