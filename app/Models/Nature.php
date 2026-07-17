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
    ];

    protected function casts(): array
    {
        return [
            'report_modality' => IncidentReportModality::class,
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
}
