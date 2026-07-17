<?php

declare(strict_types=1);

namespace App\Livewire\Operations;

use App\Models\Incident;
use App\Support\Operations\IncidentRouteContext;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.incident-route-map')]
#[Title('Percurso da viatura')]
final class IncidentRouteMap extends Component
{
    public Incident $incident;

    public function mount(Incident $incident): void
    {
        Gate::authorize('view', $incident);

        $this->incident = $incident;
    }

    public function render(): View
    {
        $routeContext = IncidentRouteContext::for($this->incident);

        return view('livewire.operations.incident-route-map', [
            'routeContext' => $routeContext,
            'routeVehicle' => $routeContext->vehicle,
            'hasDevice' => $routeContext->hasDevice(),
        ])->layoutData([
            'incident' => $this->incident,
        ]);
    }
}
