<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Shift;
use App\Models\User;

/**
 * Turnos de serviço: central pode gerir todas as bases; municipal apenas o próprio `municipio_id`.
 *
 * @see docs/migracao/entidades.md — turno
 * @see docs/migracao/regras-negocio.md
 */
final class ShiftPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasOperationalAbility('catalog.manage');
    }

    public function view(User $user, Shift $shift): bool
    {
        if (! $user->hasOperationalAbility('catalog.manage') && ! $user->hasOperationalAbility('dispatch.view')) {
            return false;
        }

        return $user->canAccessOperationalMunicipio((int) $shift->municipio_id);
    }

    /** Central e municipal podem criar turnos (escopo do município validado na camada de aplicação). */
    public function create(User $user): bool
    {
        return $user->hasOperationalAbility('catalog.manage');
    }

    public function update(User $user, Shift $shift): bool
    {
        if ($user->hasOperationalAbility('catalog.manage')) {
            return $user->canAccessOperationalMunicipio((int) $shift->municipio_id);
        }

        if ($user->hasOperationalAbility('dispatch.view')) {
            return $user->canAccessOperationalMunicipio((int) $shift->municipio_id);
        }

        return false;
    }

    public function delete(User $user, Shift $shift): bool
    {
        return $this->manage($user, (int) $shift->municipio_id);
    }

    private function manage(User $user, int $municipioId): bool
    {
        if (! $user->hasOperationalAbility('catalog.manage')) {
            return false;
        }

        return $user->canAccessOperationalMunicipio($municipioId);
    }
}
