<?php

declare(strict_types=1);

namespace App\Support\Operations\Reports;

use App\Domain\Operations\Enums\IncidentReportModality;
use App\Domain\Operations\Enums\IncidentStatus;
use App\Models\Incident;
use App\Models\User;
use App\Support\Operations\OperationalIncidentVisibility;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Agregações gerenciais de ocorrências para o módulo de relatórios.
 * Respeita a visibilidade por município via {@see OperationalIncidentVisibility}.
 */
final class IncidentReportQuery
{
    /**
     * @param  array{from?:?string,to?:?string,municipio_id?:?int,nature_id?:?int,modality?:?string}  $filters
     * @return array<string,mixed>
     */
    public function build(?User $user, array $filters = []): array
    {
        [$start, $end] = $this->resolvePeriod($filters['from'] ?? null, $filters['to'] ?? null);

        $base = $this->baseQuery($user, $start, $end, $filters);

        return [
            'period' => ['from' => $start->toDateString(), 'to' => $end->toDateString()],
            'total' => (clone $base)->count(),
            'by_modality' => $this->byModality($base),
            'by_status' => $this->byStatus($base),
            'by_day' => $this->byDay($base, $start, $end),
            'by_municipio' => $this->byMunicipio($base),
            'response_time' => $this->responseTime($base),
        ];
    }

    /**
     * @param  array{from?:?string,to?:?string,municipio_id?:?int,nature_id?:?int,modality?:?string}  $filters
     */
    private function baseQuery(?User $user, Carbon $start, Carbon $end, array $filters): Builder
    {
        $query = Incident::query()
            ->whereBetween('incidents.occurred_at', [$start, $end]);

        OperationalIncidentVisibility::constrainListing($query, $user);

        if (! empty($filters['municipio_id'])) {
            $query->where('incidents.municipio_id', (int) $filters['municipio_id']);
        }

        if (! empty($filters['nature_id'])) {
            $query->where('incidents.nature_id', (int) $filters['nature_id']);
        }

        if (! empty($filters['modality'])) {
            $query->whereHas('nature', fn (Builder $n) => $n->where('report_modality', $filters['modality']));
        }

        return $query;
    }

    /** @return list<array{key:string,label:string,count:int}> */
    private function byModality(Builder $base): array
    {
        $expr = "COALESCE(natures.report_modality, 'sem_modalidade')";

        return (clone $base)
            ->leftJoin('natures', 'incidents.nature_id', '=', 'natures.id')
            ->select([DB::raw("{$expr} AS modality"), DB::raw('COUNT(*) AS total')])
            ->groupBy(DB::raw($expr))
            ->orderByDesc('total')
            ->get()
            ->map(fn ($row): array => [
                'key' => (string) $row->modality,
                'label' => $this->modalityLabel((string) $row->modality),
                'count' => (int) $row->total,
            ])
            ->all();
    }

    /** @return list<array{key:string,label:string,count:int}> */
    private function byStatus(Builder $base): array
    {
        return (clone $base)
            // Alias evita o cast de enum do model (que quebraria a conversão para string).
            ->select([DB::raw('incidents.status AS status_value'), DB::raw('COUNT(*) AS total')])
            ->groupBy('incidents.status')
            ->orderByDesc('total')
            ->get()
            ->map(fn ($row): array => [
                'key' => (string) $row->status_value,
                'label' => IncidentStatus::tryFrom((string) $row->status_value)?->label() ?? (string) $row->status_value,
                'count' => (int) $row->total,
            ])
            ->all();
    }

    /** @return list<array{date:string,count:int}> */
    private function byDay(Builder $base, Carbon $start, Carbon $end): array
    {
        $rows = (clone $base)
            ->select([DB::raw('DATE(incidents.occurred_at) AS d'), DB::raw('COUNT(*) AS total')])
            ->groupBy(DB::raw('DATE(incidents.occurred_at)'))
            ->pluck('total', 'd');

        $out = [];
        $cursor = $start->copy()->startOfDay();
        $limit = $end->copy()->startOfDay();

        // Evita séries gigantes; agrupa por dia só se o intervalo for <= 92 dias.
        if ($cursor->diffInDays($limit) > 92) {
            return [];
        }

        while ($cursor->lte($limit)) {
            $key = $cursor->toDateString();
            $out[] = ['date' => $key, 'count' => (int) ($rows[$key] ?? 0)];
            $cursor->addDay();
        }

        return $out;
    }

    /** @return list<array{municipio:string,count:int}> */
    private function byMunicipio(Builder $base): array
    {
        return (clone $base)
            ->leftJoin('municipios', 'incidents.municipio_id', '=', 'municipios.id')
            ->select([DB::raw("COALESCE(municipios.city, 'Sem município') AS municipio"), DB::raw('COUNT(*) AS total')])
            ->groupBy(DB::raw("COALESCE(municipios.city, 'Sem município')"))
            ->orderByDesc('total')
            ->limit(15)
            ->get()
            ->map(fn ($row): array => ['municipio' => (string) $row->municipio, 'count' => (int) $row->total])
            ->all();
    }

    /** @return array{avg_dispatch_to_scene_min:?float,avg_call_to_scene_min:?float,sample:int} */
    private function responseTime(Builder $base): array
    {
        $row = (clone $base)
            ->whereNotNull('incidents.dispatched_at')
            ->whereNotNull('incidents.arrived_scene_at')
            ->select([
                DB::raw('AVG(EXTRACT(EPOCH FROM (incidents.arrived_scene_at - incidents.dispatched_at))) AS dispatch_to_scene'),
                DB::raw('AVG(EXTRACT(EPOCH FROM (incidents.arrived_scene_at - incidents.call_received_at))) AS call_to_scene'),
                DB::raw('COUNT(*) AS sample'),
            ])
            ->first();

        return [
            'avg_dispatch_to_scene_min' => $row?->dispatch_to_scene !== null ? round(((float) $row->dispatch_to_scene) / 60, 1) : null,
            'avg_call_to_scene_min' => $row?->call_to_scene !== null ? round(((float) $row->call_to_scene) / 60, 1) : null,
            'sample' => (int) ($row?->sample ?? 0),
        ];
    }

    private function modalityLabel(string $key): string
    {
        return IncidentReportModality::tryFrom($key)?->label() ?? 'Sem modalidade';
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
