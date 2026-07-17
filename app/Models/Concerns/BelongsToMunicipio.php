<?php

declare(strict_types=1);

namespace App\Models\Concerns;

use App\Models\Municipio;
use App\Support\Operations\CurrentMunicipio;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

trait BelongsToMunicipio
{
    public static function bootBelongsToMunicipio(): void
    {
        static::addGlobalScope('municipio_operacional', function (Builder $builder): void {
            $mid = CurrentMunicipio::id();
            if ($mid !== null) {
                $builder->where($builder->qualifyColumn('municipio_id'), $mid);
            }
        });

        static::creating(function (Model $model): void {
            if ($model->getAttribute('municipio_id') === null && ($mid = CurrentMunicipio::id()) !== null) {
                $model->setAttribute('municipio_id', $mid);
            }
        });
    }

    public function municipio(): BelongsTo
    {
        return $this->belongsTo(Municipio::class);
    }
}
