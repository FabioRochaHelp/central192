<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Domain\Operations\Enums\CallType;
use App\Domain\Operations\Enums\IncidentReportModality;
use App\Models\Incident;
use App\Support\Fire\FireMapFocoQuery;
use App\Support\Operations\OperationalIncidentVisibility;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\QueryException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Attributes\Title;
use Livewire\Component;
use PDOException;

#[Layout('layouts.app')]
#[Title('Dashboard')]
final class Dashboard extends Component
{
    /** Incrementado por Echo/Reverb para forçar novo render com contagens atualizadas. */
    public int $callStatsBroadcastTick = 0;

    public string $fireMapFrom = '';

    public string $fireMapTo = '';

    public string $fireMapSatelite = '';

    public function mount(): void
    {
        $this->fireMapTo = now()->toDateString();
        $this->fireMapFrom = now()->subDays((int) config('fire.dashboard_map_days', 60))->toDateString();
    }

    #[On('dashboard-call-stats-refresh')]
    public function refreshCallStatsFromBroadcast(): void
    {
        $this->callStatsBroadcastTick++;
    }

    public function loadFireMap(FireMapFocoQuery $query): void
    {
        $this->validate([
            'fireMapFrom' => ['required', 'date'],
            'fireMapTo' => ['required', 'date', 'after_or_equal:fireMapFrom'],
            'fireMapSatelite' => ['nullable', 'string', 'max:50'],
        ]);

        try {
            $focos = $query->forDashboard(
                Auth::user(),
                $this->fireMapFrom,
                $this->fireMapTo,
                $this->fireMapSatelite !== '' ? $this->fireMapSatelite : null,
            );
        } catch (QueryException|PDOException) {
            $this->addError('fireMap', __('Não foi possível conectar ao banco de focos satelitais.'));

            return;
        }

        $this->dispatch('dashboard-fire-map-updated', focos: $focos->all());
    }

    public function fireMapFullscreenUrl(): string
    {
        return route('dashboard.fire-map', array_filter([
            'from' => $this->fireMapFrom,
            'to' => $this->fireMapTo,
            'satelite' => $this->fireMapSatelite !== '' ? $this->fireMapSatelite : null,
        ]));
    }

    public function render(): View
    {
        $showCallStats = Gate::allows('viewAny', Incident::class);
        $callTypeStats = [];

        if ($showCallStats) {
            $base = Incident::query();
            OperationalIncidentVisibility::constrainListing($base, Auth::user());

            $start = now()->startOfDay();
            $end = now()->endOfDay();

            foreach (CallType::orderedForDashboard() as $type) {
                $callTypeStats[] = [
                    'code' => $type->value,
                    'label' => $type->label(),
                    'count' => (clone $base)
                        ->where('patient_call_type', $type->value)
                        ->whereBetween('occurred_at', [$start, $end])
                        ->count(),
                ];
            }
        }

        $modalityStats = $showCallStats ? $this->computeModalityStats($base) : null;

        $fireSatellites = $this->resolveFireSatellites();

        return view('livewire.dashboard', [
            'showCallStats' => $showCallStats,
            'callTypeStats' => $callTypeStats,
            'modalityStats' => $modalityStats,
            'fireSatellites' => $fireSatellites,
        ]);
    }

    /**
     * @return Collection<int, string>
     */
    private function resolveFireSatellites(): Collection
    {
        try {
            return app(FireMapFocoQuery::class)->availableSatellites();
        } catch (QueryException|PDOException) {
            return collect();
        }
    }

    /**
     * Agrupa ocorrências do mês corrente por report_modality da natureza.
     *
     * @return array{total:int,month:string,slices:array<int,array{label:string,count:int,percentage:float,color:string}>}|null
     */
    private function computeModalityStats(Builder $base): ?array
    {
        $monthStart = now()->startOfMonth();
        $monthEnd = now()->endOfMonth();

        $modalityExpr = "COALESCE(natures.report_modality, 'sem_modalidade')";

        $rows = (clone $base)
            ->select([
                DB::raw("{$modalityExpr} AS modality"),
                DB::raw('COUNT(*) AS total'),
            ])
            ->leftJoin('natures', 'incidents.nature_id', '=', 'natures.id')
            ->whereBetween('incidents.occurred_at', [$monthStart, $monthEnd])
            ->groupBy(DB::raw($modalityExpr))
            ->orderByDesc('total')
            ->get();

        $totalCount = (int) $rows->sum('total');
        $monthLabel = now()->format('m/Y');

        if ($totalCount === 0) {
            return ['total' => 0, 'month' => $monthLabel, 'slices' => []];
        }

        $colors = [
            'samu' => '#06b6d4',
            'fire_forest' => '#f97316',
            'fire_building' => '#f59e0b',
            'rescue_animal' => '#22c55e',
            'rescue_insects' => '#eab308',
            'rescue_other' => '#3b82f6',
            'sem_modalidade' => '#94a3b8',
        ];

        $slices = [];
        foreach ($rows as $row) {
            $key = $row->modality ?? 'sem_modalidade';
            $modEnum = IncidentReportModality::tryFrom($key);
            $slices[] = [
                'label' => $modEnum?->label() ?? __('Sem modalidade'),
                'count' => (int) $row->total,
                'percentage' => round($row->total / $totalCount * 100, 1),
                'color' => $colors[$key] ?? '#94a3b8',
            ];
        }

        return [
            'total' => $totalCount,
            'month' => $monthLabel,
            'slices' => $slices,
        ];
    }
}
