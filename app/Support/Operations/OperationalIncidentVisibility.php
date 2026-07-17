<?php

declare(strict_types=1);

namespace App\Support\Operations;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/** Escopo de listagem de ocorrências conforme usuário (central vs municipal e sessão da central). */
final class OperationalIncidentVisibility
{
    public static function constrainListing(Builder $query, ?User $user): Builder
    {
        if ($user === null) {
            return $query->whereRaw('1 = 0');
        }

        $mid = OperationalMunicipioSelection::current($user);

        if ($mid === null) {
            return $user->hasMultiMunicipioAccess()
                ? $query
                : $query->whereRaw('1 = 0');
        }

        if ($user->hasMultiMunicipioAccess()) {
            return $query->where(static function ($w) use ($mid): void {
                $w->where('municipio_id', $mid)->orWhereNull('municipio_id');
            });
        }

        return $query->where(static function ($w) use ($mid): void {
            $w->where('municipio_id', $mid)->orWhereNull('municipio_id');
        });
    }
}
