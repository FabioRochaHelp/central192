<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToMunicipio;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProtectedArea extends Model
{
    use BelongsToMunicipio, SoftDeletes;

    protected $fillable = [
        'municipio_id',
        'name',
        'notes',
    ];

    public function contacts(): HasMany
    {
        return $this->hasMany(ProtectedAreaContact::class);
    }

    public function incidents(): HasMany
    {
        return $this->hasMany(Incident::class);
    }
}
