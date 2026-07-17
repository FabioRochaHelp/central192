<?php

declare(strict_types=1);

namespace App\Livewire\Operations;

use App\Domain\Operations\Actions\AppendIncidentDescriptionAction;
use App\Models\Incident;
use App\Models\User;
use App\Support\Operations\IncidentRouteContext;
use App\Support\Operations\OperationalCallAlertFireReport;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Component;

#[Layout('layouts.app')]
final class IncidentOperationalDetail extends Component
{
    public Incident $incident;

    public string $descriptionAppend = '';

    public function mount(Incident $incident): void
    {
        Gate::authorize('view', $incident);

        $this->incident = $incident->load([
            'nature',
            'operationalCallAlerts',
            'nurseReport.filledBy',
            'finalReport.filledBy',
            'victims.prescriptions.items',
            'dispatches.shift.vehicle',
            'timelineEvents' => fn ($q) => $q
                ->withoutGlobalScope('municipio_operacional')
                ->orderByDesc('recorded_at')
                ->limit(100),
            'timelineEvents.actor',
        ]);
    }

    /** Reverb: stage avançado ou viatura liberada nesta ocorrência. */
    #[On('incident-detail-refresh')]
    public function refreshOperationalState(): void
    {
        $this->incident->refresh();
        $this->incident->load([
            'nature',
            'operationalCallAlerts',
            'nurseReport.filledBy',
            'finalReport.filledBy',
            'victims.prescriptions.items',
            'dispatches.shift.vehicle',
            'timelineEvents' => fn ($q) => $q
                ->withoutGlobalScope('municipio_operacional')
                ->orderByDesc('recorded_at')
                ->limit(100),
            'timelineEvents.actor',
        ]);

        if (! IncidentRouteContext::for($this->incident)->isHistoricalReplay()) {
            $this->dispatch('incident-route-map-refresh');
        }
    }

    public function routeMapFullscreenUrl(): string
    {
        return route('operations.incidents.route-map', $this->incident);
    }

    public function appendDescription(AppendIncidentDescriptionAction $action): void
    {
        $this->resetErrorBag();

        $this->validate(
            ['descriptionAppend' => ['required', 'string', 'min:3', 'max:2000']],
            ['descriptionAppend.required' => __('Informe o texto da nova anotação.')],
        );

        Gate::authorize('addObservation', $this->incident);

        /** @var User $user */
        $user = Auth::user();
        $action->execute($this->incident, $this->descriptionAppend, $user);

        $this->descriptionAppend = '';
        $this->incident->refresh();
    }

    public function render(): View
    {
        return view('livewire.operations.incident-detail', [
            'activeDispatch' => $this->incident->activeDispatch(),
            'callAlertReport' => OperationalCallAlertFireReport::fromIncident($this->incident),
            'routeContext' => IncidentRouteContext::for($this->incident),
        ]);
    }
}
