<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;
use App\Models\Vehicle;

final class VehiclePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasOperationalAbility('catalog.manage');
    }

    public function view(User $user, Vehicle $vehicle): bool
    {
        if (! $user->hasOperationalAbility('catalog.manage')) {
            return false;
        }

        return $user->canAccessOperationalMunicipio((int) $vehicle->municipio_id);
    }

    public function create(User $user): bool
    {
        return $user->hasOperationalAbility('catalog.manage');
    }

    public function update(User $user, Vehicle $vehicle): bool
    {
        return $this->manage($user, (int) $vehicle->municipio_id);
    }

    public function delete(User $user, Vehicle $vehicle): bool
    {
        return $this->manage($user, (int) $vehicle->municipio_id);
    }

    private function manage(User $user, int $municipioId): bool
    {
        if (! $user->hasOperationalAbility('catalog.manage')) {
            return false;
        }

        return $user->canAccessOperationalMunicipio($municipioId);
    }
}
