<?php

declare(strict_types=1);

namespace App\Domain\Operations\Actions;

use App\Domain\Operations\Enums\PrescriptionStatus;
use App\Domain\Operations\Services\IncidentTimelineRecorder;
use App\Models\Prescription;
use App\Models\User;
use App\Models\Victim;
use Illuminate\Support\Facades\DB;

final class CreatePrescriptionAction
{
    public function __construct(
        private IncidentTimelineRecorder $timeline,
    ) {}

    /**
     * @param  list<array{medication_name: string, quantity: int}>  $items
     */
    public function execute(
        Victim $victim,
        User $actor,
        ?int $medicalStaffId,
        ?string $description,
        array $items,
    ): Prescription {
        return DB::transaction(function () use ($victim, $actor, $medicalStaffId, $description, $items): Prescription {
            $prescription = Prescription::query()->create([
                'victim_id' => $victim->id,
                'medical_staff_id' => $medicalStaffId,
                'prescribed_by_user_id' => $actor->id,
                'status' => PrescriptionStatus::Pending,
                'description' => $description,
            ]);

            foreach ($items as $item) {
                $prescription->items()->create($item);
            }

            $incident = $victim->incident;
            $this->timeline->record($incident, 'prescription_created', [
                'victim_id' => $victim->id,
                'prescription_id' => $prescription->id,
                'items_count' => count($items),
            ], $actor);

            return $prescription->load(['items', 'victim.incident', 'medicalStaff', 'prescribedBy']);
        });
    }
}
