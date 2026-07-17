<?php

declare(strict_types=1);

namespace App\Policies;

use App\Domain\Operations\Enums\PrescriptionStatus;
use App\Models\Prescription;
use App\Models\User;
use App\Models\Victim;

final class PrescriptionPolicy
{
    public function view(User $user, Prescription $prescription): bool
    {
        return $user->can('view', $prescription->victim);
    }

    public function create(User $user, Victim $victim): bool
    {
        if (! $user->hasOperationalAbility('victim.prescribe')) {
            return false;
        }

        return (int) $victim->situacao === 1 && $user->can('view', $victim);
    }

    public function approve(User $user, Prescription $prescription): bool
    {
        if (! $user->hasOperationalAbility('victim.prescription.approve')) {
            return false;
        }

        return $prescription->status === PrescriptionStatus::Pending
            && $user->can('view', $prescription);
    }
}
