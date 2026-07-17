<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Campos específicos de Incêndio em Edificação.
 *
 * @see docs/migracao/modalidades-relatorios.md — fire_building_reports
 */
final class FireBuildingReport extends Model
{
    protected $primaryKey = 'incident_final_report_id';

    public $incrementing = false;

    protected $fillable = [
        'incident_final_report_id',
        'building_type',
        'construction_type',
        'floors_total',
        'floors_affected',
        'affected_area_m2',
        'rooms_affected',
        'probable_cause',
        'fire_origin_location',
        'hazmat_present',
        'hazmat_description',
        'occupants_at_incident',
        'animals_rescued',
        'animals_deceased',
        'residents_displaced',
        'damage_level',
        'vehicle_involved',
        'external_agencies',
        'actions_taken',
        'final_status',
        'business_name',
        'business_activity',
    ];

    protected function casts(): array
    {
        return [
            'rooms_affected' => 'array',
            'hazmat_present' => 'boolean',
            'vehicle_involved' => 'boolean',
        ];
    }

    public function finalReport(): BelongsTo
    {
        return $this->belongsTo(IncidentFinalReport::class, 'incident_final_report_id');
    }
}
