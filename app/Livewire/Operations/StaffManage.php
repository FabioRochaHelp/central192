<?php

declare(strict_types=1);

namespace App\Livewire\Operations;

use App\Domain\Operations\Enums\StaffCargo;
use App\Domain\Operations\Enums\StaffDocumentType;
use App\Models\Municipio;
use App\Models\Staff;
use App\Support\Operations\OperationalMunicipioSelection;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/** Cadastro de efetivo (docs/migracao/entidades.md — efetivo). */
#[Layout('layouts.app')]
#[Title('Efetivo')]
final class StaffManage extends Component
{
    public string $name = '';

    public ?string $document_type = '';

    public ?string $document_number = '';

    public ?string $cpf = '';

    public ?string $email = '';

    public ?string $phone = '';

    public ?string $cargo = null;

    public ?int $editingId = null;

    public string $message = '';

    public bool $cpfTaken = false;

    /** Base escolhida na central (espelha sessão `operational_municipio_id`). */
    public ?string $selectedOperationalMunicipioId = null;

    public function mount(): void
    {
        Gate::authorize('viewAny', Staff::class);
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
        $this->name = '';
        $this->document_type = '';
        $this->document_number = '';
        $this->cpf = '';
        $this->email = '';
        $this->phone = '';
        $this->cargo = null;
        $this->editingId = null;
        $this->cpfTaken = false;
    }

    public function checkDuplicateCpf(): void
    {
        // Remove formatação para comparar apenas os dígitos
        $digits = preg_replace('/\D/', '', $this->cpf ?? '');

        if (strlen($digits) !== 11) {
            $this->cpfTaken = false;

            return;
        }

        // CPF é nacional — verifica duplicidade em toda a tabela
        $this->cpfTaken = Staff::query()
            ->whereRaw("REPLACE(REPLACE(cpf, '.', ''), '-', '') = ?", [$digits])
            ->when($this->editingId !== null, fn ($q) => $q->where('id', '!=', $this->editingId))
            ->exists();
    }

    public function edit(int $id): void
    {
        $this->resetErrorBag();
        $s = Staff::query()->findOrFail($id);
        $this->authorize('update', $s);
        $this->editingId = $s->id;
        $this->name = $s->name;
        $this->document_type = $s->document_type ?? '';
        $this->document_number = $s->document_number ?? '';
        $this->cpf = $s->cpf ?? '';
        $this->email = $s->email ?? '';
        $this->phone = $s->phone ?? '';
        $this->cargo = $s->cargo?->value;
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
            'name' => ['required', 'string', 'max:255'],
            'document_type' => ['nullable', 'string', Rule::enum(StaffDocumentType::class)],
            'document_number' => ['nullable', 'string', 'max:64'],
            'cpf' => ['nullable', 'string', 'max:14'],
            'email' => ['nullable', 'email:rfc,dns', 'max:255'],
            'phone' => ['nullable', 'string', 'max:64'],
            'cargo' => ['nullable', 'string', Rule::enum(StaffCargo::class)],
        ]);

        $payload = [
            'municipio_id' => $mid,
            'name' => $validated['name'],
            'document_type' => $validated['document_type'] ?: null,
            'document_number' => $validated['document_number'] ?: null,
            'cpf' => $validated['cpf'] ?: null,
            'email' => $validated['email'] ?: null,
            'phone' => $validated['phone'] ?: null,
            'cargo' => $validated['cargo'],
        ];

        if ($this->editingId !== null) {
            $s = Staff::query()->findOrFail($this->editingId);
            $this->authorize('update', $s);
            $s->update($payload);
            $this->message = __('Registro atualizado.');
        } else {
            $this->authorize('create', Staff::class);
            Staff::query()->create($payload);
            $this->message = __('Registro criado.');
        }

        $this->resetForm();
    }

    public function delete(int $id): void
    {
        $this->resetErrorBag();
        $s = Staff::query()->findOrFail($id);
        $this->authorize('delete', $s);
        $s->delete();
        $this->message = __('Registro excluído.');
        if ($this->editingId === $id) {
            $this->resetForm();
        }
    }

    public function render(): View
    {
        $mid = $this->municipioId();
        $q = Staff::query()->orderBy('name');
        if ($mid !== null) {
            $q->where('municipio_id', $mid);
        }

        return view('livewire.operations.staff-manage', [
            'scopeMunicipioId' => $mid,
            'staffMembers' => $q->get(),
            'operationalMunicipios' => Auth::user()?->isOperationalCentral()
                ? Municipio::query()->where('active', true)->orderBy('razao_social')->get()
                : collect(),
            'cargoCases' => StaffCargo::cases(),
            'documentTypeCases' => StaffDocumentType::cases(),
        ]);
    }
}
