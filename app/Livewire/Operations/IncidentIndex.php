<?php

declare(strict_types=1);

namespace App\Livewire\Operations;

use App\Domain\Operations\Enums\IncidentStatus;
use App\Models\Incident;
use App\Support\Operations\OperationalIncidentVisibility;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/** Lista de ocorrências com filtros equivalentes às listas legadas (abertas / campo / QTA / encerradas). */
#[Layout('layouts.app')]
#[Title('Ocorrências')]
final class IncidentIndex extends Component
{
    use WithPagination;

    /** @var 'open'|'field'|'pending_nurse'|'pending_final'|'qta'|'closed'|'cancelled'|'logged'|'all' */
    #[Url(as: 'f')]
    public string $filter = 'open';

    public function mount(): void
    {
        Gate::authorize('viewAny', Incident::class);
    }

    /** Reverb: nova ocorrência ou encerramento força atualização da lista. */
    #[On('incident-index-refresh')]
    public function refreshFromBroadcast(): void {}

    public function setFilter(string $value): void
    {
        $allowed = ['open', 'field', 'pending_nurse', 'pending_final', 'qta', 'closed', 'cancelled', 'logged', 'all'];
        if (in_array($value, $allowed, true)) {
            $this->filter = $value;
            $this->resetPage();
        }
    }

    public function render(): View
    {
        $query = Incident::query()
            ->with(['municipio', 'nature'])
            ->orderByDesc('occurred_at');

        OperationalIncidentVisibility::constrainListing($query, Auth::user());

        $query = match ($this->filter) {
            'open' => $query->where('status', IncidentStatus::Open),
            'field' => $query->whereIn('status', [IncidentStatus::Dispatched, IncidentStatus::InProgress]),
            'pending_nurse' => $query->where('status', IncidentStatus::PendingNurseReport),
            'pending_final' => $query->where('status', IncidentStatus::PendingFinalReport),
            'qta' => $query->where('status', IncidentStatus::Qta),
            'closed' => $query->where('status', IncidentStatus::Closed),
            'cancelled' => $query->where('status', IncidentStatus::Cancelled),
            'logged' => $query->where('status', IncidentStatus::Logged),
            // "Todas" exclui registros simples (Logged) — fluxo operacional apenas
            default => $query->where('status', '!=', IncidentStatus::Logged->value),
        };

        $incidents = $query->paginate(15);

        return view('livewire.operations.incident-index', [
            'incidents' => $incidents,
            'counts' => $this->scopedCounts(),
        ]);
    }

    /**
     * Contagens respeitam o escopo global do município (usuário municipal ou sessão da central).
     *
     * @return array<string, int>
     */
    private function scopedCounts(): array
    {
        $base = Incident::query();
        OperationalIncidentVisibility::constrainListing($base, Auth::user());

        return [
            'open' => (clone $base)->where('status', IncidentStatus::Open)->count(),
            'field' => (clone $base)->whereIn('status', [IncidentStatus::Dispatched, IncidentStatus::InProgress])->count(),
            'pending_nurse' => (clone $base)->where('status', IncidentStatus::PendingNurseReport)->count(),
            'pending_final' => (clone $base)->where('status', IncidentStatus::PendingFinalReport)->count(),
            'qta' => (clone $base)->where('status', IncidentStatus::Qta)->count(),
            'closed' => (clone $base)->where('status', IncidentStatus::Closed)->count(),
            'cancelled' => (clone $base)->where('status', IncidentStatus::Cancelled)->count(),
            'logged' => (clone $base)->where('status', IncidentStatus::Logged)->count(),
            // "Todas" = apenas ocorrências operacionais (sem Logged)
            'all' => (clone $base)->where('status', '!=', IncidentStatus::Logged->value)->count(),
        ];
    }
}
