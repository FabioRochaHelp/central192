<?php

declare(strict_types=1);

namespace App\Domain\Operations\Actions;

use App\Domain\Operations\Enums\PrescriptionStatus;
use App\Domain\Operations\Services\IncidentTimelineRecorder;
use App\Models\Prescription;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class ApprovePrescriptionAction
{
    public function __construct(
        private IncidentTimelineRecorder $timeline,
    ) {}

    public function execute(Prescription $prescription, User $actor): Prescription
    {
        return DB::transaction(function () use ($prescription, $actor): Prescription {
            /** @var Prescription $locked */
            $locked = Prescription::query()
                ->whereKey($prescription->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($locked->status !== PrescriptionStatus::Pending) {
                throw ValidationException::withMessages([
                    'prescription' => __('A prescrição não está mais pendente.'),
                ]);
            }

            $locked->update([
                'status' => PrescriptionStatus::Approved,
                'approved_by_user_id' => $actor->id,
                'approved_at' => now(),
            ]);

            $locked->load(['victim.incident', 'items']);
            $this->timeline->record($locked->victim->incident, 'prescription_approved', [
                'victim_id' => $locked->victim_id,
                'prescription_id' => $locked->id,
            ], $actor);

            return $locked->fresh(['items', 'victim.incident', 'medicalStaff', 'prescribedBy', 'approvedBy']);
        });
    }
}
