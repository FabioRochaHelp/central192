<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\Operations\Enums\ManchesterRisk;
use App\Domain\Operations\Enums\RegulationDecision;
use App\Domain\Operations\Enums\RegulationResource;
use App\Models\Concerns\BelongsToMunicipio;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Regulação médica de uma ocorrência (um registro por ocorrência).
 *
 * @see docs/regulacao/plano-implementacao.md
 */
final class IncidentRegulation extends Model
{
    use BelongsToMunicipio;

    protected $fillable = [
        'municipio_id',
        'incident_id',
        'regulator_user_id',
        'status',
        'priority',
        'diagnostic_hypothesis',
        'recommended_resource',
        'guidance_notes',
        'refusal_reason',
        'transfer_target',
        'assumed_at',
        'decided_at',
        'response_time_seconds',
    ];

    protected function casts(): array
    {
        return [
            'status' => RegulationDecision::class,
            'priority' => ManchesterRisk::class,
            'recommended_resource' => RegulationResource::class,
            'assumed_at' => 'datetime',
            'decided_at' => 'datetime',
            'response_time_seconds' => 'integer',
        ];
    }

    public function incident(): BelongsTo
    {
        return $this->belongsTo(Incident::class);
    }

    public function regulator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'regulator_user_id');
    }
}
