<?php

declare(strict_types=1);

namespace App\Support\Operations;

use App\Models\User;
use Illuminate\Contracts\Auth\Authenticatable;

/**
 * Município efetivo para formulários: usuário municipal usa `users.municipio_id`;
 * perfis multi-base usam `session('operational_municipio_id')` (mesma regra do CCO).
 */
final class OperationalMunicipioSelection
{
    public static function current(?Authenticatable $user): ?int
    {
        if ($user instanceof User && $user->hasMultiMunicipioAccess()) {
            $sid = session('operational_municipio_id');

            return $sid !== null ? (int) $sid : null;
        }

        if ($user instanceof User && $user->municipio_id !== null) {
            return (int) $user->municipio_id;
        }

        $sid = session('operational_municipio_id');

        return $sid !== null ? (int) $sid : null;
    }
}
