<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Relatório do enfermeiro preenchido após encerramento (equivalente fluxo legado de relatórios). */
final class IncidentNurseReport extends Model
{
    protected $fillable = [
        'incident_id',
        'user_id',
        'clinical_evolution',
        'conduct_summary',
        'destination_notes',
        'submitted_at',
    ];

    protected function casts(): array
    {
        return [
            'submitted_at' => 'datetime',
        ];
    }

    public function incident(): BelongsTo
    {
        return $this->belongsTo(Incident::class);
    }

    public function filledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
