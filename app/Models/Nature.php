<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\Operations\Enums\IncidentReportModality;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Natureza operacional — cadastro global (parâmetro da ocorrência). */
class Nature extends Model
{
    protected $fillable = [
        'nature_type_id',
        'name',
        'report_modality',
        'requires_medical_regulation',
    ];

    protected function casts(): array
    {
        return [
            'report_modality' => IncidentReportModality::class,
            'requires_medical_regulation' => 'boolean',
        ];
    }

    public function natureType(): BelongsTo
    {
        return $this->belongsTo(NatureType::class);
    }

    public function reportModality(): ?IncidentReportModality
    {
        return $this->report_modality;
    }

    public function isFireModality(): bool
    {
        return in_array($this->report_modality, [
            IncidentReportModality::FireForest,
            IncidentReportModality::FireBuilding,
        ], true);
    }

    /**
     * Ocorrências desta natureza passam por regulação médica antes do despacho.
     * Disparado pela modalidade SAMU; o boolean `requires_medical_regulation`
     * funciona como override manual para naturezas de outra modalidade.
     */
    public function requiresMedicalRegulation(): bool
    {
        return $this->report_modality === IncidentReportModality::Samu
            || (bool) $this->requires_medical_regulation;
    }
}
