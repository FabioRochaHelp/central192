<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Tipo de natureza — cadastro global (parâmetro da ocorrência). */
class NatureType extends Model
{
    protected $fillable = [
        'name',
    ];

    public function natures(): HasMany
    {
        return $this->hasMany(Nature::class);
    }
}
