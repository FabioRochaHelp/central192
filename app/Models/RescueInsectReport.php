<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class RescueInsectReport extends Model
{
    protected $primaryKey = 'incident_final_report_id';

    public $incrementing = false;

    protected $fillable = [
        'incident_final_report_id',
        'insect_type',
        'insect_species',
        'colony_size_estimate',
        'nest_location_type',
        'nest_location_detail',
        'technique_used',
        'colony_destination',
        'people_stung',
        'sting_severity',
        'prehospital_care',
        'prehospital_description',
        'equipment_used',
    ];

    protected function casts(): array
    {
        return [
            'prehospital_care' => 'boolean',
        ];
    }

    public function finalReport(): BelongsTo
    {
        return $this->belongsTo(IncidentFinalReport::class, 'incident_final_report_id');
    }
}
