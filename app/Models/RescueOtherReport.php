<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class RescueOtherReport extends Model
{
    protected $primaryKey = 'incident_final_report_id';

    public $incrementing = false;

    protected $fillable = [
        'incident_final_report_id',
        'rescue_subtype',
        'victim_count',
        'situation_description',
        'victim_condition',
        'entrapment_description',
        'rescue_technique',
        'equipment_used',
        'hospital_transport',
        'hospital_name',
        'outcome',
        'duration_minutes',
    ];

    protected function casts(): array
    {
        return [
            'hospital_transport' => 'boolean',
        ];
    }

    public function finalReport(): BelongsTo
    {
        return $this->belongsTo(IncidentFinalReport::class, 'incident_final_report_id');
    }
}
