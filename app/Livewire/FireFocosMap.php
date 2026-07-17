<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Models\Incident;
use App\Support\Fire\FireMapFocoQuery;
use Illuminate\Contracts\View\View;
use Illuminate\Database\QueryException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use PDOException;

#[Layout('layouts.fire-map')]
#[Title('Focos de incêndio')]
final class FireFocosMap extends Component
{
    public string $fireMapFrom = '';

    public string $fireMapTo = '';

    public string $fireMapSatelite = '';

    /** @var array<int, array<string, mixed>> */
    public array $mapFocos = [];

    public function mount(): void
    {
        Gate::authorize('viewAny', Incident::class);

        $defaultFrom = now()->subDays((int) config('fire.dashboard_map_days', 60))->toDateString();

        $this->fireMapTo = (string) request()->query('to', now()->toDateString());
        $this->fireMapFrom = (string) request()->query('from', $defaultFrom);
        $this->fireMapSatelite = (string) request()->query('satelite', '');

        $this->refreshMapFocos();
    }

    public function loadFireMap(): void
    {
        $this->validate([
            'fireMapFrom' => ['required', 'date'],
            'fireMapTo' => ['required', 'date', 'after_or_equal:fireMapFrom'],
            'fireMapSatelite' => ['nullable', 'string', 'max:50'],
        ]);

        $this->refreshMapFocos();

        $this->dispatch('dashboard-fire-map-updated', focos: $this->mapFocos);
    }

    public function render(): View
    {
        return view('livewire.fire-focos-map', [
            'fireSatellites' => $this->resolveFireSatellites(),
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

    private function refreshMapFocos(): void
    {
        try {
            $this->mapFocos = app(FireMapFocoQuery::class)
                ->forDashboard(
                    Auth::user(),
                    $this->fireMapFrom,
                    $this->fireMapTo,
                    $this->fireMapSatelite !== '' ? $this->fireMapSatelite : null,
                )
                ->all();

            $this->resetErrorBag('fireMap');
        } catch (QueryException|PDOException) {
            $this->mapFocos = [];
            $this->addError('fireMap', __('Não foi possível conectar ao banco de focos satelitais.'));
        }
    }
}
