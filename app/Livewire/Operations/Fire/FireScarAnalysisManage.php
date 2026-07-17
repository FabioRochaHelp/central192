<?php

declare(strict_types=1);

namespace App\Livewire\Operations\Fire;

use App\Domain\Fire\Enums\FireScarSeverity;
use App\Domain\Fire\Enums\FireScarStatus;
use App\Domain\Operations\Enums\IncidentReportModality;
use App\Jobs\RequestFireScarAnalysis;
use App\Models\FireScarAnalysis;
use App\Models\Incident;
use App\Support\Operations\OperationalIncidentVisibility;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Gestão das análises de cicatriz de incêndio (burn scar).
 *
 * Fluxo principal: listar ocorrências de um período e, ao selecionar, solicitar a
 * análise puxando os dados da própria ocorrência (coordenada, município, data).
 * Campos digitáveis (bioma, sensor, data pré-fogo) são opcionais e sobrescrevem o padrão.
 */
#[Layout('layouts.app')]
#[Title('Cicatrizes de incêndio')]
final class FireScarAnalysisManage extends Component
{
    // ── Filtro de período ────────────────────────────────────────────────────
    public string $from = '';

    public string $to = '';

    public bool $onlyForest = true;

    /** @var array<int,int> IDs de ocorrências selecionadas */
    public array $selected = [];

    // ── Overrides opcionais (padrão vem da ocorrência) ───────────────────────
    public string $overrideBioma = '';

    public string $overrideSensor = 'Sentinel-2';

    public string $overridePreFireDate = '';

    // ── Análise avulsa por coordenada (ex.: a partir do mapa de focos) ───────
    public string $latitude = '';

    public string $longitude = '';

    public string $municipio = '';

    public string $estado = '';

    public string $bioma = '';

    public string $message = '';

    public function mount(): void
    {
        abort_unless(Auth::user()?->isOperationalCentral(), 403);

        $this->to = now()->toDateString();
        $this->from = now()->subDays(30)->toDateString();

        // Pré-preenchimento da seção avulsa por query string (mapa de focos).
        $this->latitude = (string) request()->query('lat', '');
        $this->longitude = (string) request()->query('lng', request()->query('lon', ''));
        $this->bioma = (string) request()->query('bioma', '');
        $this->municipio = (string) request()->query('municipio', '');
        $this->estado = (string) request()->query('estado', '');
    }

    public function updated(string $property): void
    {
        // Ao mudar o filtro, limpa a seleção para não enviar ocorrências fora da lista.
        if (in_array($property, ['from', 'to', 'onlyForest'], true)) {
            $this->selected = [];
        }
    }

    /** @return Collection<int,Incident> */
    private function incidents(): Collection
    {
        [$start, $end] = $this->resolvePeriod();

        $query = Incident::query()
            ->whereBetween('occurred_at', [$start, $end])
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->with(['nature', 'municipio']);

        OperationalIncidentVisibility::constrainListing($query, Auth::user());

        if ($this->onlyForest) {
            $query->whereHas('nature', fn (Builder $n) => $n->where('report_modality', IncidentReportModality::FireForest->value));
        }

        return $query->orderByDesc('occurred_at')->limit(300)->get();
    }

    public function selectAll(): void
    {
        $this->selected = $this->incidents()->pluck('id')->all();
    }

    public function clearSelection(): void
    {
        $this->selected = [];
    }

    /** Solicita a análise de cicatriz para cada ocorrência selecionada. */
    public function requestSelected(): void
    {
        $this->resetErrorBag();

        if ($this->selected === []) {
            $this->addError('selected', __('Selecione ao menos uma ocorrência.'));

            return;
        }

        $incidents = Incident::query()
            ->whereIn('id', $this->selected)
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->with('municipio')
            ->get();

        $count = 0;

        foreach ($incidents as $incident) {
            $analysis = FireScarAnalysis::create([
                'incident_id' => $incident->id,
                'latitude' => $incident->latitude,
                'longitude' => $incident->longitude,
                'municipio' => $incident->city ?? $incident->municipio?->city,
                'estado' => $incident->municipio?->state,
                'bioma' => $this->overrideBioma !== '' ? $this->overrideBioma : null,
                'pre_fire_date' => $this->overridePreFireDate !== '' ? $this->overridePreFireDate : null,
                'post_fire_date' => $incident->occurred_at?->toDateString(),
                'sensor' => $this->overrideSensor !== '' ? $this->overrideSensor : 'Sentinel-2',
                'status' => FireScarStatus::Pendente,
                'requested_at' => now(),
                'created_by' => Auth::id(),
            ]);

            RequestFireScarAnalysis::dispatch($analysis->id);
            $count++;
        }

        $this->selected = [];
        $this->message = trans_choice('Análise solicitada para :count ocorrência.|Análise solicitada para :count ocorrências.', $count, ['count' => $count]);
    }

    /** Análise avulsa por coordenada (sem ocorrência vinculada). */
    public function createStandalone(): void
    {
        $this->resetErrorBag();

        $validated = $this->validate([
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'municipio' => ['nullable', 'string', 'max:255'],
            'estado' => ['nullable', 'string', 'size:2'],
            'bioma' => ['nullable', 'string', 'max:255'],
            'overridePreFireDate' => ['nullable', 'date'],
        ]);

        $analysis = FireScarAnalysis::create([
            'latitude' => $validated['latitude'],
            'longitude' => $validated['longitude'],
            'municipio' => $validated['municipio'] ?: null,
            'estado' => $validated['estado'] ? mb_strtoupper($validated['estado']) : null,
            'bioma' => $validated['bioma'] ?: null,
            'pre_fire_date' => $this->overridePreFireDate ?: null,
            'sensor' => $this->overrideSensor !== '' ? $this->overrideSensor : 'Sentinel-2',
            'status' => FireScarStatus::Pendente,
            'requested_at' => now(),
            'created_by' => Auth::id(),
        ]);

        RequestFireScarAnalysis::dispatch($analysis->id);

        $this->reset(['latitude', 'longitude', 'municipio', 'estado', 'bioma']);
        $this->message = __('Análise avulsa solicitada.');
    }

    public function retry(int $id): void
    {
        $analysis = FireScarAnalysis::findOrFail($id);

        $analysis->forceFill([
            'status' => FireScarStatus::Pendente,
            'error_message' => null,
            'requested_at' => now(),
        ])->save();

        RequestFireScarAnalysis::dispatch($analysis->id);

        $this->message = __('Reprocessamento solicitado.');
    }

    public function delete(int $id): void
    {
        FireScarAnalysis::whereKey($id)->delete();
        $this->message = __('Análise removida.');
    }

    public function render(): View
    {
        $analyses = FireScarAnalysis::query()
            ->with('incident:id,talao,dispatch_year')
            ->latest('id')
            ->limit(100)
            ->get();

        return view('livewire.operations.fire.fire-scar-analysis-manage', [
            'incidents' => $this->incidents(),
            'analyses' => $analyses,
            'hasPending' => $analyses->contains(
                fn (FireScarAnalysis $a): bool => ! $a->status->isTerminal(),
            ),
            'severities' => FireScarSeverity::cases(),
        ]);
    }

    /** @return array{0:Carbon,1:Carbon} */
    private function resolvePeriod(): array
    {
        $end = $this->to !== '' ? Carbon::parse($this->to)->endOfDay() : now()->endOfDay();
        $start = $this->from !== '' ? Carbon::parse($this->from)->startOfDay() : $end->copy()->subDays(30)->startOfDay();

        if ($start->gt($end)) {
            [$start, $end] = [$end->copy()->startOfDay(), $start->copy()->endOfDay()];
        }

        return [$start, $end];
    }
}
