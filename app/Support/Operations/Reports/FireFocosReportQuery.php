<?php

declare(strict_types=1);

namespace App\Support\Operations\Reports;

use App\Models\FocoSatelite;
use App\Models\User;
use App\Support\Fire\FocosBoundingBox;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Agregações gerenciais de focos de calor (INPE) para o módulo de relatórios.
 * Lê da conexão `fire_monitor` (mesma fonte do mapa de focos).
 */
final class FireFocosReportQuery
{
    /**
     * @param  array{from?:?string,to?:?string,estado?:?string,bioma?:?string,satelite?:?string}  $filters
     * @return array<string,mixed>
     */
    public function build(?User $user, array $filters = []): array
    {
        [$start, $end] = $this->resolvePeriod($filters['from'] ?? null, $filters['to'] ?? null);

        $base = $this->baseQuery($user, $start, $end, $filters);

        return [
            'period' => ['from' => $start->toDateString(), 'to' => $end->toDateString()],
            'total' => (clone $base)->count(),
            'by_satelite' => $this->groupBy($base, 'satelite_id', 'Sem satélite'),
            'by_bioma' => $this->groupBy($base, 'bioma', 'Sem bioma'),
            'by_municipio' => $this->groupBy($base, 'municipio', 'Sem município', 15),
            'by_day' => $this->byDay($base),
        ];
    }

    /**
     * @param  array{from?:?string,to?:?string,estado?:?string,bioma?:?string,satelite?:?string}  $filters
     */
    private function baseQuery(?User $user, Carbon $start, Carbon $end, array $filters): Builder
    {
        $query = FocoSatelite::query()
            ->whereRaw('COALESCE(data_hora_local, data_hora_gmt, data_insercao) >= ?', [$start])
            ->whereRaw('COALESCE(data_hora_local, data_hora_gmt, data_insercao) <= ?', [$end]);

        // UF explícita usa o rótulo; sem UF, restringe pela área geográfica padrão (SP).
        if (! empty($filters['estado'])) {
            $query->where('estado', $filters['estado']);
        } else {
            FocosBoundingBox::apply($query);
        }

        if (! empty($filters['bioma'])) {
            $query->where('bioma', $filters['bioma']);
        }

        if (! empty($filters['satelite'])) {
            $query->where('satelite_id', $filters['satelite']);
        }

        return $query;
    }

    /** @return list<array{label:string,count:int}> */
    private function groupBy(Builder $base, string $column, string $nullLabel, int $limit = 30): array
    {
        return (clone $base)
            ->select([DB::raw("COALESCE({$column}, '{$nullLabel}') AS label"), DB::raw('COUNT(*) AS total')])
            ->groupBy(DB::raw("COALESCE({$column}, '{$nullLabel}')"))
            ->orderByDesc('total')
            ->limit($limit)
            ->get()
            ->map(fn ($row): array => ['label' => (string) $row->label, 'count' => (int) $row->total])
            ->all();
    }

    /** @return list<array{date:string,count:int}> */
    private function byDay(Builder $base): array
    {
        return (clone $base)
            ->select([
                DB::raw('DATE(COALESCE(data_hora_local, data_hora_gmt, data_insercao)) AS d'),
                DB::raw('COUNT(*) AS total'),
            ])
            ->groupBy(DB::raw('DATE(COALESCE(data_hora_local, data_hora_gmt, data_insercao))'))
            ->orderBy('d')
            ->limit(120)
            ->get()
            ->map(fn ($row): array => ['date' => (string) $row->d, 'count' => (int) $row->total])
            ->all();
    }

    /** @return array{0:Carbon,1:Carbon} */
    private function resolvePeriod(?string $from, ?string $to): array
    {
        $end = $to !== null && $to !== '' ? Carbon::parse($to)->endOfDay() : now()->endOfDay();
        $start = $from !== null && $from !== '' ? Carbon::parse($from)->startOfDay() : $end->copy()->subDays(30)->startOfDay();

        if ($start->gt($end)) {
            [$start, $end] = [$end->copy()->startOfDay(), $start->copy()->endOfDay()];
        }

        return [$start, $end];
    }
}
