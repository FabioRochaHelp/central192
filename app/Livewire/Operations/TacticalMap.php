<?php

declare(strict_types=1);

namespace App\Livewire\Operations;

use App\Domain\Operations\Enums\IncidentStatus;
use App\Domain\Operations\Enums\OperationalCallAlertStatus;
use App\Domain\Operations\Events\OperationalCallAlertClusterUpdated;
use App\Models\Incident;
use App\Models\IncidentDispatch;
use App\Support\Operations\OperationalCallAlertGrouper;
use App\Support\Operations\OperationalIncidentVisibility;
use App\Support\Operations\OperationalMunicipioSelection;
use App\Support\Operations\TacticalMapVehicleQuery;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.tactical-map')]
#[Title('Mapa tático')]
final class TacticalMap extends Component
{
    /** Reverb: re-renderiza contadores e lista de ocorrências no mapa tático. */
    #[On('tactical-map-refresh')]
    public function refreshFromBroadcast(): void {}

    #[On('tactical-map-alert-cluster-abort')]
    public function abortCallAlertCluster(string $locationKey): void
    {
        $user = Auth::user();
        if ($user === null || ! $user->hasOperationalAbility('incident.create')) {
            return;
        }

        $alerts = OperationalCallAlertGrouper::activeAtLocationKey($locationKey);
        if ($alerts->isEmpty()) {
            return;
        }

        $lat = (float) $alerts->first()->latitude;
        $lng = (float) $alerts->first()->longitude;

        foreach ($alerts as $alert) {
            if (! $alert->isActionable()) {
                continue;
            }

            $alert->update([
                'status' => OperationalCallAlertStatus::Aborted,
                'aborted_by' => $user->id,
                'aborted_at' => now(),
            ]);
        }

        OperationalCallAlertClusterUpdated::dispatchForLocation($lat, $lng);
    }

    #[On('tactical-map-alert-cluster-create-incident')]
    public function createIncidentFromCallAlertCluster(string $locationKey): void
    {
        $user = Auth::user();
        if ($user === null || ! $user->hasOperationalAbility('incident.create')) {
            return;
        }

        $alert = OperationalCallAlertGrouper::latestActiveAtLocationKey($locationKey);
        if ($alert === null || ! $alert->isActionable()) {
            return;
        }

        $this->dispatch('operational-call-intake', ...$alert->toIntakePrefill());
    }

    public function render(): View
    {
        $mid = OperationalMunicipioSelection::current(Auth::user());

        $openIncidentsQuery = Incident::query()
            ->with(['nature', 'municipio'])
            ->where('status', IncidentStatus::Open);

        OperationalIncidentVisibility::constrainListing($openIncidentsQuery, Auth::user());

        $openIncidents = $openIncidentsQuery->orderByDesc('occurred_at')->get();

        $activeDispatches = IncidentDispatch::query()
            ->with(['incident.nature'])
            ->whereNull('deleted_at')
            ->when($mid !== null, fn ($q) => $q->where('municipio_id', $mid))
            ->get();

        $allActiveIncidents = $openIncidents->merge(
            $activeDispatches->map(fn (IncidentDispatch $d) => $d->incident)->filter()
        )->unique('id');

        $mapIncidents = $allActiveIncidents
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->map(fn (Incident $i) => [
                'id' => $i->id,
                'lat' => (float) $i->latitude,
                'lng' => (float) $i->longitude,
                'talao' => $i->talao,
                'year' => $i->dispatch_year,
                'nature' => $i->nature?->name ?? '—',
                'status' => $i->status->value,
                'url' => route('operations.incidents.show', $i),
            ])
            ->values();

        $mapAlertClusters = OperationalCallAlertGrouper::allClusters();

        try {
            $mapVehicles = TacticalMapVehicleQuery::mapPayload($mid);
        } catch (\Throwable) {
            $mapVehicles = collect();
        }

        return view('livewire.operations.tactical-map', [
            'mapIncidents' => $mapIncidents,
            'mapAlertClusters' => $mapAlertClusters,
            'mapVehicles' => $mapVehicles,
        ]);
    }
}
