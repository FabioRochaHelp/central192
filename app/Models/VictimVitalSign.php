<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Registro seriado de sinais vitais (vitima_has_sinais). */
final class VictimVitalSign extends Model
{
    protected $fillable = [
        'victim_id',
        'recorded_at',
        'blood_pressure_systolic',
        'blood_pressure_diastolic',
        'heart_rate',
        'respiratory_rate',
        'spo2',
        'temperature',
        'blood_glucose',
        'glasgow_eye',
        'glasgow_verbal',
        'glasgow_motor',
        'glasgow_total',
        'neurological_notes',
        'dominant_side',
    ];

    protected function casts(): array
    {
        return [
            'recorded_at' => 'datetime',
            'temperature' => 'decimal:1',
        ];
    }

    public function victim(): BelongsTo
    {
        return $this->belongsTo(Victim::class);
    }
}
