<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Municipio;
use App\Models\User;

/**
 * Bases operacionais (`municipios`): cadastro restrito ao operador central.
 *
 * @see docs/migracao/banco-dados.md — contract → municipios
 */
final class MunicipioPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isOperationalCentral();
    }

    public function view(User $user, Municipio $municipio): bool
    {
        return $user->isOperationalCentral();
    }

    public function create(User $user): bool
    {
        return $user->isOperationalCentral();
    }

    public function update(User $user, Municipio $municipio): bool
    {
        return $user->isOperationalCentral();
    }

    public function delete(User $user, Municipio $municipio): bool
    {
        return $user->isOperationalCentral();
    }
}
