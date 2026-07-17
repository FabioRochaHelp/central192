<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FocoSatelite extends Model
{
    protected $connection = 'fire_monitor';

    protected $table = 'focos_satelite';

    public $timestamps = false;

    protected $fillable = [
        'satelite_id',
        'sensor',
        'pais',
        'latitude',
        'longitude',
        'municipio',
        'estado',
        'bioma',
        'data_hora_gmt',
        'data_hora_local',
        'frp',
        'temperatura_brilho',
        'confianca',
        'area_foco',
        'angulo_zenital',
        'pixel_size_scan',
        'pixel_size_track',
        'precipitacao_acumulada',
        'dias_sem_chuva',
        'risco_fogo_inpe',
        'data_insercao',
        'status_integracao',
    ];

    protected function casts(): array
    {
        return [
            'latitude' => 'decimal:6',
            'longitude' => 'decimal:6',
            'data_hora_gmt' => 'datetime',
            'data_hora_local' => 'datetime',
            'frp' => 'decimal:2',
            'temperatura_brilho' => 'decimal:2',
            'confianca' => 'integer',
            'risco_fogo_inpe' => 'decimal:2',
            'data_insercao' => 'datetime',
        ];
    }
}
