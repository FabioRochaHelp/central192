<?php

declare(strict_types=1);

namespace App\Support\Fire;

use Illuminate\Contracts\Database\Query\Builder;

/**
 * Restringe uma consulta de focos à área geográfica padrão (bounding box de SP).
 *
 * A maioria dos focos vem sem `estado` preenchido, então filtramos por coordenada
 * em vez do rótulo de UF — padroniza a região do consórcio sem descartar registros.
 */
final class FocosBoundingBox
{
    /**
     * @template TQuery of Builder
     *
     * @param  TQuery  $query
     * @return TQuery
     */
    public static function apply(Builder $query): Builder
    {
        $bbox = config('fire.focos_bbox');

        $query
            ->whereBetween('latitude', [$bbox['min_lat'], $bbox['max_lat']])
            ->whereBetween('longitude', [$bbox['min_lon'], $bbox['max_lon']]);

        // Descarta focos rotulados com outra UF que caem na borda do retângulo;
        // mantém os sem UF (NULL) e os da própria UF do consórcio.
        $label = $bbox['state_label'] ?? null;

        if ($label !== null && $label !== '') {
            $query->where(function ($q) use ($label): void {
                $q->whereNull('estado')->orWhere('estado', $label);
            });
        }

        return $query;
    }
}
