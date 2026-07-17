<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;

/**
 * Gestão de usuários do sistema: apenas administrador central (`users_type_legacy == 1`).
 *
 * @see docs/migracao/entidades.md — usuário e tipo (users_type)
 */
final class UserPolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->isCentralAdministrator();
    }

    public function view(User $actor, User $model): bool
    {
        return $actor->isCentralAdministrator();
    }

    public function create(User $actor): bool
    {
        return $actor->isCentralAdministrator();
    }

    public function update(User $actor, User $model): bool
    {
        return $actor->isCentralAdministrator();
    }

    public function delete(User $actor, User $model): bool
    {
        if (! $actor->isCentralAdministrator()) {
            return false;
        }

        return $actor->id !== $model->id;
    }
}
