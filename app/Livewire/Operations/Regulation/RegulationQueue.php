<?php

declare(strict_types=1);

namespace App\Livewire\Operations\Regulation;

use App\Domain\Operations\Actions\AssumeRegulationAction;
use App\Domain\Operations\Enums\IncidentStatus;
use App\Models\Incident;
use App\Models\User;
use App\Support\Operations\DispatchQueueIncidentSorter;
use App\Support\Operations\OperationalIncidentVisibility;
use App\Support\Operations\OperationalMunicipioSelection;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Attributes\Title;
use Livewire\Component;
use RuntimeException;

/**
 * Fila de regulação médica — ocorrências aguardando ou em regulação.
 *
 * @see docs/regulacao/plano-implementacao.md
 */
#[Layout('layouts.app')]
#[Title('Regulação médica')]
final class RegulationQueue extends Component
{
    public string $boardMessage = '';

    public function mount(): void
    {
        abort_unless(Auth::user()?->hasOperationalAbility('regulation.view'), 403);
    }

    /** Reverb: eventos de regulação/despacho forçam re-render. */
    #[On('dispatch-board-refresh')]
    public function refreshFromBroadcast(): void
    {
        // Re-render automático ao receber o evento.
    }

    public function assume(int $incidentId, AssumeRegulationAction $action): void
    {
        $this->resetErrorBag();
        $this->boardMessage = '';

        /** @var Incident|null $incident */
        $incident = Incident::query()->find($incidentId);
        if ($incident === null) {
            return;
        }

        Gate::authorize('regulate', $incident);

        /** @var User $user */
        $user = Auth::user();

        try {
            $action->execute($incident->id, $user);
        } catch (RuntimeException $e) {
            $this->addError('board', $e->getMessage());

            return;
        }

        $this->redirect(route('operations.incidents.regulation', $incident), navigate: true);
    }

    public function render(): View
    {
        $query = Incident::query()
            ->with(['nature', 'municipio', 'regulation.regulator'])
            ->withCount('callRequests')
            ->whereIn('status', IncidentStatus::regulationQueue());

        OperationalIncidentVisibility::constrainListing($query, Auth::user());

        $mid = OperationalMunicipioSelection::current(Auth::user());
        if ($mid !== null) {
            $query->where('municipio_id', $mid);
        }

        $incidents = DispatchQueueIncidentSorter::sort($query->get());

        return view('livewire.operations.regulation.regulation-queue', [
            'incidents' => $incidents,
            'pendingCount' => $incidents->where('status', IncidentStatus::PendingRegulation)->count(),
            'inRegulationCount' => $incidents->where('status', IncidentStatus::InRegulation)->count(),
        ]);
    }
}
