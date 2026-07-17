<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class RescueAnimalReport extends Model
{
    protected $primaryKey = 'incident_final_report_id';

    public $incrementing = false;

    protected $fillable = [
        'incident_final_report_id',
        'animal_category',
        'animal_species',
        'animal_breed',
        'animal_size',
        'entrapment_type',
        'entrapment_height_m',
        'animal_condition_arrival',
        'equipment_used',
        'outcome',
        'owner_name',
        'owner_phone',
        'destination_notes',
    ];

    public function finalReport(): BelongsTo
    {
        return $this->belongsTo(IncidentFinalReport::class, 'incident_final_report_id');
    }
}
