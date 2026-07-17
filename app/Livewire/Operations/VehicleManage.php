<?php

declare(strict_types=1);

namespace App\Livewire\Operations;

use App\Integrations\Traccar\TraccarService;
use App\Integrations\Tracking\Providers\Ssx\SsxService;
use App\Models\Municipio;
use App\Models\Vehicle;
use App\Support\Operations\OperationalMunicipioSelection;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/** Cadastro de viaturas (docs/migracao/entidades.md — viatura). */
#[Layout('layouts.app')]
#[Title('Viaturas')]
final class VehicleManage extends Component
{
    public ?string $plate = '';

    public ?string $prefix = '';

    public ?string $make = '';

    public ?string $model = '';

    public ?int $year = null;

    public ?string $device_id = '';

    public ?string $ssx_integration_code = '';

    public ?int $status_legacy = null;

    public ?int $editingId = null;

    public string $message = '';

    public bool $prefixTaken = false;

    /** @var array<int, array{id: int, name: string, uniqueId: string, status: string}> */
    public array $traccarDeviceList = [];

    public bool $traccarAvailable = false;

    public ?string $traccarError = null;

    /** @var array<int, array{code: string, name: string, fixTime: string, valid: bool}> */
    public array $ssxUnitList = [];

    public bool $ssxAvailable = false;

    public ?string $ssxError = null;

    /** Base escolhida na central (espelha sessão `operational_municipio_id`). */
    public ?string $selectedOperationalMunicipioId = null;

    public function mount(TraccarService $traccar, SsxService $ssx): void
    {
        Gate::authorize('viewAny', Vehicle::class);

        $user = Auth::user();
        if ($user !== null && $user->isOperationalCentral()) {
            $this->selectedOperationalMunicipioId = session('operational_municipio_id') !== null
                ? (string) session('operational_municipio_id')
                : null;
        }

        $this->loadTraccarDevices($traccar);
        $this->loadSsxUnits($ssx);
    }

    public function refreshTraccarDevices(TraccarService $traccar): void
    {
        $this->loadTraccarDevices($traccar);
    }

    public function refreshSsxUnits(SsxService $ssx): void
    {
        $this->loadSsxUnits($ssx);
    }

    private function loadSsxUnits(SsxService $ssx): void
    {
        $this->ssxError = null;

        try {
            $this->ssxUnitList = $ssx->units()->all();
            $this->ssxAvailable = true;
        } catch (\Throwable) {
            $this->ssxUnitList = [];
            $this->ssxAvailable = false;
            $this->ssxError = __('Não foi possível conectar ao SSX. Verifique a configuração ou insira o código de integração manualmente.');
        }
    }

    /**
     * @return list<string>
     */
    private function allowedSsxCodes(): array
    {
        $codes = collect($this->ssxUnitList)
            ->pluck('code')
            ->map(fn ($code): string => (string) $code)
            ->all();

        if ($this->editingId !== null) {
            $current = Vehicle::query()->find($this->editingId)?->ssx_integration_code;

            if ($current !== null && ! in_array((string) $current, $codes, true)) {
                $codes[] = (string) $current;
            }
        }

        return $codes;
    }

    private function loadTraccarDevices(TraccarService $traccar): void
    {
        $this->traccarError = null;

        try {
            $this->traccarDeviceList = $traccar->devices()
                ->map(fn ($d) => [
                    'id' => $d->id,
                    'name' => $d->name,
                    'uniqueId' => $d->uniqueId,
                    'status' => $d->status,
                    'isReporting' => $d->isReportingAt(),
                ])
                ->all();
            $this->traccarAvailable = true;
        } catch (\Throwable) {
            $this->traccarDeviceList = [];
            $this->traccarAvailable = false;
            $this->traccarError = __('Não foi possível conectar ao Traccar. Verifique a configuração ou insira o device ID manualmente.');
        }
    }

    /**
     * @return list<string>
     */
    private function allowedTraccarDeviceIds(): array
    {
        $ids = collect($this->traccarDeviceList)
            ->pluck('id')
            ->map(fn ($id): string => (string) $id)
            ->all();

        if ($this->editingId !== null) {
            $current = Vehicle::query()->find($this->editingId)?->device_id;

            if ($current !== null && ! in_array((string) $current, $ids, true)) {
                $ids[] = (string) $current;
            }
        }

        return $ids;
    }

    public function updatedSelectedOperationalMunicipioId(?string $value): void
    {
        if ($value === null || $value === '') {
            session()->forget('operational_municipio_id');
        } else {
            session(['operational_municipio_id' => (int) $value]);
        }
    }

    private function municipioId(): ?int
    {
        $user = Auth::user();
        if ($user?->municipio_id !== null) {
            return (int) $user->municipio_id;
        }
        if ($user?->isOperationalCentral()) {
            return $this->selectedOperationalMunicipioId !== null && $this->selectedOperationalMunicipioId !== ''
                ? (int) $this->selectedOperationalMunicipioId
                : null;
        }

        return OperationalMunicipioSelection::current($user);
    }

    public function resetForm(): void
    {
        $this->plate = '';
        $this->prefix = '';
        $this->make = '';
        $this->model = '';
        $this->year = null;
        $this->device_id = '';
        $this->ssx_integration_code = '';
        $this->status_legacy = null;
        $this->editingId = null;
        $this->prefixTaken = false;
    }

    public function checkDuplicatePrefix(): void
    {
        if (trim($this->prefix ?? '') === '') {
            $this->prefixTaken = false;

            return;
        }

        $mid = $this->municipioId();
        if ($mid === null) {
            $this->prefixTaken = false;

            return;
        }

        $this->prefixTaken = Vehicle::query()
            ->where('prefix', $this->prefix)
            ->where('municipio_id', $mid)
            ->when($this->editingId !== null, fn ($q) => $q->where('id', '!=', $this->editingId))
            ->exists();
    }

    public function edit(int $id): void
    {
        $this->resetErrorBag();
        $v = Vehicle::query()->findOrFail($id);
        $this->authorize('update', $v);
        $this->editingId = $v->id;
        $this->plate = $v->plate;
        $this->prefix = $v->prefix;
        $this->make = $v->make;
        $this->model = $v->model;
        $this->year = $v->year;
        $this->device_id = $v->device_id ?? '';
        $this->ssx_integration_code = $v->ssx_integration_code ?? '';
        $this->status_legacy = $v->status_legacy;
    }

    public function save(): void
    {
        $this->resetErrorBag();
        $mid = $this->municipioId();
        if ($mid === null) {
            $this->addError('scope', __('Defina o município na central ou use um usuário municipal.'));

            return;
        }

        $validated = $this->validate([
            'plate' => [
                'nullable',
                'string',
                'max:32',
                Rule::unique('vehicles', 'plate')->where(fn ($q) => $q->where('municipio_id', $mid))->ignore($this->editingId),
            ],
            'prefix' => [
                'nullable',
                'string',
                'max:32',
                Rule::unique('vehicles', 'prefix')->where(fn ($q) => $q->where('municipio_id', $mid))->ignore($this->editingId),
            ],
            'make' => ['nullable', 'string', 'max:255'],
            'model' => ['nullable', 'string', 'max:255'],
            'year' => ['nullable', 'integer', 'min:1950', 'max:2100'],
            'device_id' => [
                'nullable',
                'string',
                'max:255',
                'regex:/^\d+$/',
                Rule::when(
                    $this->traccarAvailable && filled($this->device_id),
                    [Rule::in($this->allowedTraccarDeviceIds())],
                ),
            ],
            'ssx_integration_code' => [
                'nullable',
                'string',
                'max:255',
                Rule::when(
                    $this->ssxAvailable && filled($this->ssx_integration_code),
                    [Rule::in($this->allowedSsxCodes())],
                ),
                Rule::unique('vehicles', 'ssx_integration_code')->where(fn ($q) => $q->where('municipio_id', $mid))->ignore($this->editingId),
            ],
            'status_legacy' => ['nullable', 'integer', 'min:0', 'max:255'],
        ]);

        $payload = [
            'municipio_id' => $mid,
            'plate' => $validated['plate'] ?: null,
            'prefix' => $validated['prefix'] ?: null,
            'make' => $validated['make'] ?: null,
            'model' => $validated['model'] ?: null,
            'year' => $validated['year'],
            'device_id' => $validated['device_id'] ?: null,
            'ssx_integration_code' => $validated['ssx_integration_code'] ?: null,
            'status_legacy' => $validated['status_legacy'],
        ];

        if ($this->editingId !== null) {
            $v = Vehicle::query()->findOrFail($this->editingId);
            $this->authorize('update', $v);
            $v->update($payload);
            $this->message = __('Viatura atualizada.');
        } else {
            $this->authorize('create', Vehicle::class);
            Vehicle::query()->create($payload);
            $this->message = __('Viatura criada.');
        }

        $this->resetForm();
    }

    public function delete(int $id): void
    {
        $this->resetErrorBag();
        $v = Vehicle::query()->findOrFail($id);
        $this->authorize('delete', $v);
        if ($v->shifts()->where('ends_at', '>=', now())->exists()) {
            $this->addError('delete', __('Existem turnos ainda vigentes para esta viatura.'));

            return;
        }
        $v->delete();
        $this->message = __('Viatura excluída.');
        if ($this->editingId === $id) {
            $this->resetForm();
        }
    }

    public function render(): View
    {
        $mid = $this->municipioId();
        $q = Vehicle::query()->orderBy('prefix');
        if ($mid !== null) {
            $q->where('municipio_id', $mid);
        }

        $usedDeviceIds = Vehicle::query()
            ->whereNotNull('device_id')
            ->when($this->editingId !== null, fn ($q) => $q->where('id', '!=', $this->editingId))
            ->pluck('device_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $availableTraccarDevices = collect($this->traccarDeviceList)
            ->filter(fn ($d) => ! in_array((int) $d['id'], $usedDeviceIds, true));

        $usedSsxCodes = Vehicle::query()
            ->whereNotNull('ssx_integration_code')
            ->when($this->editingId !== null, fn ($q) => $q->where('id', '!=', $this->editingId))
            ->pluck('ssx_integration_code')
            ->map(fn ($code) => (string) $code)
            ->all();

        $availableSsxUnits = collect($this->ssxUnitList)
            ->filter(fn ($u) => ! in_array((string) $u['code'], $usedSsxCodes, true));

        return view('livewire.operations.vehicle-manage', [
            'scopeMunicipioId' => $mid,
            'vehicles' => $q->get(),
            'operationalMunicipios' => Auth::user()?->isOperationalCentral()
                ? Municipio::query()->where('active', true)->orderBy('razao_social')->get()
                : collect(),
            'traccarDevices' => $availableTraccarDevices,
            'traccarAvailable' => $this->traccarAvailable,
            'ssxUnits' => $availableSsxUnits,
            'ssxAvailable' => $this->ssxAvailable,
        ]);
    }
}
