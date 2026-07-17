<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\Fire\Enums\FireScarSeverity;
use App\Domain\Fire\Enums\FireScarStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Análise de cicatriz de incêndio (burn scar) — resultado do serviço externo.
 *
 * @see .agents/skills/wildfire-scar-analysis/SKILL.md — esquema de saída JSON
 */
final class FireScarAnalysis extends Model
{
    protected $table = 'fire_scar_analyses';

    protected $fillable = [
        'incident_id',
        'latitude',
        'longitude',
        'municipio',
        'estado',
        'bioma',
        'pre_fire_date',
        'post_fire_date',
        'sensor',
        'bands_used',
        'resolution_m',
        'nbr_pre',
        'nbr_post',
        'dnbr',
        'rbr',
        'ndvi_pre',
        'ndvi_post',
        'bai',
        'severity_class',
        'dnbr_range',
        'confidence',
        'area_ha',
        'perimeter_km',
        'geometry_geojson',
        'vegetation_type',
        'notes',
        'status',
        'external_ref',
        'error_message',
        'requested_at',
        'completed_at',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
            'pre_fire_date' => 'date',
            'post_fire_date' => 'date',
            'bands_used' => 'array',
            'resolution_m' => 'integer',
            'nbr_pre' => 'decimal:4',
            'nbr_post' => 'decimal:4',
            'dnbr' => 'decimal:4',
            'rbr' => 'decimal:4',
            'ndvi_pre' => 'decimal:4',
            'ndvi_post' => 'decimal:4',
            'bai' => 'decimal:4',
            'area_ha' => 'decimal:2',
            'perimeter_km' => 'decimal:2',
            'geometry_geojson' => 'array',
            'status' => FireScarStatus::class,
            'requested_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function incident(): BelongsTo
    {
        return $this->belongsTo(Incident::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** Classe de severidade normalizada (enum) a partir do rótulo do serviço ou do dNBR. */
    public function severity(): ?FireScarSeverity
    {
        return FireScarSeverity::tryFromLabel($this->severity_class)
            ?? ($this->dnbr !== null ? FireScarSeverity::fromDnbr((float) $this->dnbr) : null);
    }

    public function isStandalone(): bool
    {
        return $this->incident_id === null;
    }
}
