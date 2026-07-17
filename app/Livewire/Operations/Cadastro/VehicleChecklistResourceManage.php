<?php

declare(strict_types=1);

namespace App\Livewire\Operations\Cadastro;

use App\Models\Municipio;
use App\Models\VehicleChecklistResource;
use App\Support\Operations\OperationalMunicipioSelection;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Recursos do check-list')]
final class VehicleChecklistResourceManage extends Component
{
    public string $formName = '';

    public string $formUnitOfMeasure = '';

    public ?int $editingId = null;

    public string $message = '';

    public ?string $selectedOperationalMunicipioId = null;

    public function mount(): void
    {
        Gate::authorize('viewAny', VehicleChecklistResource::class);

        $user = Auth::user();
        if ($user !== null && $user->isOperationalCentral()) {
            $this->selectedOperationalMunicipioId = session('operational_municipio_id') !== null
                ? (string) session('operational_municipio_id')
                : null;
        }
    }

    public function updatedSelectedOperationalMunicipioId(?string $value): void
    {
        if ($value === null || $value === '') {
            session()->forget('operational_municipio_id');
        } else {
            session(['operational_municipio_id' => (int) $value]);
        }

        $this->resetForm();
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
        $this->formName = '';
        $this->formUnitOfMeasure = '';
        $this->editingId = null;
    }

    public function edit(int $id): void
    {
        $this->resetErrorBag();
        $row = VehicleChecklistResource::query()->findOrFail($id);
        $this->authorize('update', $row);
        $this->editingId = $row->id;
        $this->formName = $row->name;
        $this->formUnitOfMeasure = $row->unit_of_measure;
    }

    public function save(): void
    {
        $this->resetErrorBag();
        $mid = $this->municipioId();
        if ($mid === null) {
            $this->addError('scope', __('Defina a base (município) para cadastrar recursos.'));

            return;
        }

        $validated = $this->validate([
            'formName' => [
                'required',
                'string',
                'max:255',
                Rule::unique('vehicle_checklist_resources', 'name')
                    ->where(fn ($q) => $q->where('municipio_id', $mid))
                    ->ignore($this->editingId),
            ],
            'formUnitOfMeasure' => ['required', 'string', 'max:64'],
        ]);

        $payload = [
            'municipio_id' => $mid,
            'name' => $validated['formName'],
            'unit_of_measure' => $validated['formUnitOfMeasure'],
        ];

        if ($this->editingId !== null) {
            $row = VehicleChecklistResource::query()->findOrFail($this->editingId);
            $this->authorize('update', $row);
            $row->update($payload);
            $this->message = __('Recurso atualizado.');
        } else {
            $this->authorize('create', VehicleChecklistResource::class);
            VehicleChecklistResource::query()->create($payload);
            $this->message = __('Recurso criado.');
        }

        $this->resetForm();
    }

    public function delete(int $id): void
    {
        $this->resetErrorBag();
        $row = VehicleChecklistResource::query()->findOrFail($id);
        $this->authorize('delete', $row);
        $row->delete();
        $this->message = __('Recurso excluído.');
        if ($this->editingId === $id) {
            $this->resetForm();
        }
    }

    public function render(): View
    {
        $mid = $this->municipioId();

        $itemsQuery = VehicleChecklistResource::query()->orderBy('name');
        if ($mid !== null) {
            $itemsQuery->where('municipio_id', $mid);
        }

        return view('livewire.operations.cadastro.vehicle-checklist-resource-manage', [
            'scopeMunicipioId' => $mid,
            'items' => $mid !== null ? $itemsQuery->get() : collect(),
            'operationalMunicipios' => Auth::user()?->isOperationalCentral()
                ? Municipio::query()->where('active', true)->orderBy('razao_social')->get()
                : collect(),
        ]);
    }
}
