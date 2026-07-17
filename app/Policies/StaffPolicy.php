<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Staff;
use App\Models\User;

final class StaffPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasOperationalAbility('catalog.manage');
    }

    public function view(User $user, Staff $staff): bool
    {
        if (! $user->hasOperationalAbility('catalog.manage')) {
            return false;
        }

        return $user->canAccessOperationalMunicipio((int) $staff->municipio_id);
    }

    public function create(User $user): bool
    {
        return $user->hasOperationalAbility('catalog.manage');
    }

    public function update(User $user, Staff $staff): bool
    {
        return $this->manage($user, (int) $staff->municipio_id);
    }

    public function delete(User $user, Staff $staff): bool
    {
        return $this->manage($user, (int) $staff->municipio_id);
    }

    private function manage(User $user, int $municipioId): bool
    {
        if (! $user->hasOperationalAbility('catalog.manage')) {
            return false;
        }

        return $user->canAccessOperationalMunicipio($municipioId);
    }
}
