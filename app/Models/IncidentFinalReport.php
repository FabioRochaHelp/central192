<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\Operations\Enums\IncidentReportModality;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Relatório final CB — base comum; sub-tabela específica por modalidade.
 *
 * @see docs/migracao/modalidades-relatorios.md — incident_final_reports
 */
final class IncidentFinalReport extends Model
{
    protected $fillable = [
        'incident_id',
        'user_id',
        'modality',
        'victims_rescued',
        'victims_injured',
        'victims_deceased',
        'resources_summary',
        'external_support',
        'observations',
        'submitted_at',
    ];

    protected function casts(): array
    {
        return [
            'modality' => IncidentReportModality::class,
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

    public function fireForestReport(): HasOne
    {
        return $this->hasOne(FireForestReport::class);
    }

    public function fireBuildingReport(): HasOne
    {
        return $this->hasOne(FireBuildingReport::class);
    }

    public function rescueAnimalReport(): HasOne
    {
        return $this->hasOne(RescueAnimalReport::class);
    }

    public function rescueInsectReport(): HasOne
    {
        return $this->hasOne(RescueInsectReport::class);
    }

    public function rescueOtherReport(): HasOne
    {
        return $this->hasOne(RescueOtherReport::class);
    }
}
