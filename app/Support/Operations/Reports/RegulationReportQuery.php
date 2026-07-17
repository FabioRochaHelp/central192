<?php

declare(strict_types=1);

namespace App\Support\Operations\Reports;

use App\Domain\Operations\Enums\ManchesterRisk;
use App\Domain\Operations\Enums\RegulationDecision;
use App\Domain\Operations\Enums\RegulationResource;
use App\Models\IncidentRegulation;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Agregações gerenciais da regulação médica (indicadores SAMU).
 *
 * @see docs/regulacao/plano-implementacao.md
 */
final class RegulationReportQuery
{
    /**
     * @param  array{from?:?string,to?:?string,municipio_id?:?int,regulator_id?:?int,decision?:?string,priority?:?string}  $filters
     * @return array<string,mixed>
     */
    public function build(?User $user, array $filters = []): array
    {
        [$start, $end] = $this->resolvePeriod($filters['from'] ?? null, $filters['to'] ?? null);

        $base = $this->baseQuery($start, $end, $filters);

        $total = (clone $base)->count();
        $byDecision = $this->byDecision($base);

        return [
            'period' => ['from' => $start->toDateString(), 'to' => $end->toDateString()],
            'total' => $total,
            'by_decision' => $byDecision,
            'by_priority' => $this->byPriority($base),
            'by_resource' => $this->byResource($base),
            'by_regulator' => $this->byRegulator($base),
            'response_time' => $this->responseTime($base),
            'guidance_pct' => $this->pctFor($byDecision, RegulationDecision::MedicalGuidance, $total),
            'dispatch_pct' => $this->pctFor($byDecision, RegulationDecision::DispatchResource, $total),
        ];
    }

    /**
     * @param  array{municipio_id?:?int,regulator_id?:?int,decision?:?string,priority?:?string}  $filters
     */
    private function baseQuery(Carbon $start, Carbon $end, array $filters): Builder
    {
        $query = IncidentRegulation::query()
            ->withoutGlobalScope('municipio_operacional')
            ->whereNotNull('incident_regulations.decided_at')
            ->whereBetween('incident_regulations.decided_at', [$start, $end]);

        if (! empty($filters['municipio_id'])) {
            $query->where('incident_regulations.municipio_id', (int) $filters['municipio_id']);
        }

        if (! empty($filters['regulator_id'])) {
            $query->where('incident_regulations.regulator_user_id', (int) $filters['regulator_id']);
        }

        if (! empty($filters['decision'])) {
            $query->where('incident_regulations.status', $filters['decision']);
        }

        if (! empty($filters['priority'])) {
            $query->where('incident_regulations.priority', $filters['priority']);
        }

        return $query;
    }

    /** @return list<array{key:string,label:string,count:int}> */
    private function byDecision(Builder $base): array
    {
        return (clone $base)
            ->select(['incident_regulations.status AS decision', DB::raw('COUNT(*) AS total')])
            ->whereNotNull('incident_regulations.status')
            ->groupBy('incident_regulations.status')
            ->orderByDesc('total')
            ->get()
            ->map(fn ($row): array => [
                'key' => (string) $row->decision,
                'label' => RegulationDecision::tryFrom((string) $row->decision)?->label() ?? (string) $row->decision,
                'count' => (int) $row->total,
            ])
            ->all();
    }

    /** @return list<array{key:string,label:string,count:int}> */
    private function byPriority(Builder $base): array
    {
        return (clone $base)
            // Alias distinto evita o cast de enum do model (priority → ManchesterRisk).
            ->select([DB::raw('incident_regulations.priority AS priority_value'), DB::raw('COUNT(*) AS total')])
            ->whereNotNull('incident_regulations.priority')
            ->groupBy('incident_regulations.priority')
            ->orderByDesc('total')
            ->get()
            ->map(fn ($row): array => [
                'key' => (string) $row->priority_value,
                'label' => ManchesterRisk::tryFrom((string) $row->priority_value)?->label() ?? (string) $row->priority_value,
                'count' => (int) $row->total,
            ])
            ->all();
    }

    /** @return list<array{key:string,label:string,count:int}> */
    private function byResource(Builder $base): array
    {
        return (clone $base)
            ->select(['incident_regulations.recommended_resource AS resource', DB::raw('COUNT(*) AS total')])
            ->whereNotNull('incident_regulations.recommended_resource')
            ->groupBy('incident_regulations.recommended_resource')
            ->orderByDesc('total')
            ->get()
            ->map(fn ($row): array => [
                'key' => (string) $row->resource,
                'label' => RegulationResource::tryFrom((string) $row->resource)?->label() ?? (string) $row->resource,
                'count' => (int) $row->total,
            ])
            ->all();
    }

    /** @return list<array{name:string,count:int,avg_response_min:?float}> */
    private function byRegulator(Builder $base): array
    {
        return (clone $base)
            ->leftJoin('users', 'incident_regulations.regulator_user_id', '=', 'users.id')
            ->select([
                DB::raw("COALESCE(users.name, 'Sem médico') AS regulator"),
                DB::raw('COUNT(*) AS total'),
                DB::raw('AVG(incident_regulations.response_time_seconds) AS avg_response'),
            ])
            ->groupBy(DB::raw("COALESCE(users.name, 'Sem médico')"))
            ->orderByDesc('total')
            ->limit(30)
            ->get()
            ->map(fn ($row): array => [
                'name' => (string) $row->regulator,
                'count' => (int) $row->total,
                'avg_response_min' => $row->avg_response !== null ? round(((float) $row->avg_response) / 60, 1) : null,
            ])
            ->all();
    }

    /** @return array{avg_response_min:?float,sample:int} */
    private function responseTime(Builder $base): array
    {
        $row = (clone $base)
            ->whereNotNull('incident_regulations.response_time_seconds')
            ->select([
                DB::raw('AVG(incident_regulations.response_time_seconds) AS avg_response'),
                DB::raw('COUNT(*) AS sample'),
            ])
            ->first();

        return [
            'avg_response_min' => $row?->avg_response !== null ? round(((float) $row->avg_response) / 60, 1) : null,
            'sample' => (int) ($row?->sample ?? 0),
        ];
    }

    /**
     * @param  list<array{key:string,label:string,count:int}>  $byDecision
     */
    private function pctFor(array $byDecision, RegulationDecision $decision, int $total): ?float
    {
        if ($total === 0) {
            return null;
        }

        foreach ($byDecision as $row) {
            if ($row['key'] === $decision->value) {
                return round($row['count'] * 100 / $total, 1);
            }
        }

        return 0.0;
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
