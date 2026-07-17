<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Célula da matriz corporal persistida por vítima (região + tipo de lesão + catálogo). */
class VictimInjuryMatrixEntry extends Model
{
    protected $fillable = [
        'victim_id',
        'matrix_region',
        'matrix_lesion',
        'injury_site_id',
    ];

    public function victim(): BelongsTo
    {
        return $this->belongsTo(Victim::class);
    }

    public function injurySite(): BelongsTo
    {
        return $this->belongsTo(InjurySite::class);
    }
}
