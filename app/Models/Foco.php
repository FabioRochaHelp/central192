<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

final class Foco extends Model
{
    protected $table = 'focos';

    protected $fillable = [
        'origem_id',
        'satelite',
        'sensor',
        'pais',
        'estado',
        'municipio',
        'bioma',
        'latitude',
        'longitude',
        'data_hora_gmt',
        'data_hora_brasilia',
        'risco_fogo',
        'temperatura',
        'confianca',
        'status',
        'recebido_em',
    ];

    protected function casts(): array
    {
        return [
            'latitude' => 'decimal:6',
            'longitude' => 'decimal:6',
            'data_hora_gmt' => 'datetime',
            'data_hora_brasilia' => 'datetime',
            'risco_fogo' => 'decimal:2',
            'temperatura' => 'decimal:2',
            'confianca' => 'integer',
            'recebido_em' => 'datetime',
        ];
    }
}
