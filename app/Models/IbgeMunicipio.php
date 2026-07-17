<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class IbgeMunicipio extends Model
{
    protected $fillable = [
        'codigo_ibge',
        'nome',
        'uf',
        'codigo_uf',
        'area_km2',
        'latitude',
        'longitude',
    ];

    protected function casts(): array
    {
        return [
            'area_km2' => 'decimal:3',
            'latitude' => 'decimal:8',
            'longitude' => 'decimal:8',
        ];
    }

    public function municipios(): HasMany
    {
        return $this->hasMany(Municipio::class);
    }
}
