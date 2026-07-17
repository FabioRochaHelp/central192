<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;
use App\Models\VehicleChecklistResource;

final class VehicleChecklistResourcePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasOperationalAbility('catalog.manage');
    }

    public function view(User $user, VehicleChecklistResource $vehicleChecklistResource): bool
    {
        if (! $user->hasOperationalAbility('catalog.manage')) {
            return false;
        }

        return $user->canAccessOperationalMunicipio((int) $vehicleChecklistResource->municipio_id);
    }

    public function create(User $user): bool
    {
        return $user->hasOperationalAbility('catalog.manage');
    }

    public function update(User $user, VehicleChecklistResource $vehicleChecklistResource): bool
    {
        return $this->manage($user, (int) $vehicleChecklistResource->municipio_id);
    }

    public function delete(User $user, VehicleChecklistResource $vehicleChecklistResource): bool
    {
        return $this->manage($user, (int) $vehicleChecklistResource->municipio_id);
    }

    private function manage(User $user, int $municipioId): bool
    {
        if (! $user->hasOperationalAbility('catalog.manage')) {
            return false;
        }

        return $user->canAccessOperationalMunicipio($municipioId);
    }
}
