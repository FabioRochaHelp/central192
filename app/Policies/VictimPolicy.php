<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;
use App\Models\Victim;

/**
 * Registro clínico da vítima na ocorrência.
 *
 * @see docs/migracao/banco-dados.md — vitima e tabelas filhas
 */
final class VictimPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasOperationalAbility('victim.record');
    }

    public function view(User $user, Victim $victim): bool
    {
        if (! $user->hasOperationalAbility('victim.record')) {
            return false;
        }

        return $user->can('view', $victim->incident);
    }

    public function update(User $user, Victim $victim): bool
    {
        return $this->view($user, $victim);
    }

    public function delete(User $user, Victim $victim): bool
    {
        return $this->update($user, $victim);
    }
}
