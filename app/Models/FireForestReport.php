<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Campos específicos de Incêndio Florestal.
 *
 * @see docs/migracao/modalidades-relatorios.md — fire_forest_reports
 */
final class FireForestReport extends Model
{
    protected $primaryKey = 'incident_final_report_id';

    public $incrementing = false;

    protected $fillable = [
        'incident_final_report_id',
        'affected_area_ha',
        'vegetation_type',
        'fire_behavior',
        'probable_cause',
        'discovery_source',
        'temperature_celsius',
        'humidity_percent',
        'wind_speed_kmh',
        'wind_direction',
        'affected_coordinates',
        'vehicles_used',
        'personnel_count',
        'aircraft_used',
        'aircraft_description',
        'external_agencies',
        'actions_taken',
        'fauna_damage',
        'fauna_damage_description',
        'structures_affected',
        'people_evacuated',
        'final_status',
        'control_achieved_at',
        'extinction_achieved_at',
    ];

    protected function casts(): array
    {
        return [
            'vehicles_used' => 'array',
            'external_agencies' => 'array',
            'aircraft_used' => 'boolean',
            'fauna_damage' => 'boolean',
            'control_achieved_at' => 'datetime',
            'extinction_achieved_at' => 'datetime',
        ];
    }

    public function finalReport(): BelongsTo
    {
        return $this->belongsTo(IncidentFinalReport::class, 'incident_final_report_id');
    }
}
