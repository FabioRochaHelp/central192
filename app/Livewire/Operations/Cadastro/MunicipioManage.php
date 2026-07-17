<?php

declare(strict_types=1);

namespace App\Livewire\Operations\Cadastro;

use App\Models\Municipio;
use App\Services\MunicipioGeoreferenceService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/** Cadastro de bases (`municipios`) — segmentação usada em efetivo, viaturas e ocorrências. */
#[Layout('layouts.app')]
#[Title('Bases')]
final class MunicipioManage extends Component
{
    public string $razao_social = '';

    public ?string $cnpj = '';

    public ?string $ie = '';

    public ?string $phone = '';

    public ?string $zipcode = '';

    public ?string $address = '';

    public ?string $number = '';

    public ?string $district = '';

    public ?string $city = '';

    public ?string $state = '';

    public bool $active = true;

    public ?string $dispatchContactRamal = '';

    public ?string $dispatchContactPhone = '';

    public ?string $dispatchContactWhatsapp = '';

    public ?int $editingId = null;

    public string $message = '';

    public bool $razaoSocialTaken = false;

    public function mount(): void
    {
        Gate::authorize('viewAny', Municipio::class);
    }

    public function resetForm(): void
    {
        $this->razao_social = '';
        $this->cnpj = '';
        $this->ie = '';
        $this->phone = '';
        $this->zipcode = '';
        $this->address = '';
        $this->number = '';
        $this->district = '';
        $this->city = '';
        $this->state = '';
        $this->active = true;
        $this->dispatchContactRamal = '';
        $this->dispatchContactPhone = '';
        $this->dispatchContactWhatsapp = '';
        $this->editingId = null;
        $this->razaoSocialTaken = false;
    }

    public function checkDuplicateRazaoSocial(): void
    {
        if (trim($this->razao_social) === '') {
            $this->razaoSocialTaken = false;

            return;
        }

        $this->razaoSocialTaken = Municipio::query()
            ->where('razao_social', $this->razao_social)
            ->whereNull('deleted_at')
            ->when($this->editingId !== null, fn ($q) => $q->where('id', '!=', $this->editingId))
            ->exists();
    }

    public function edit(int $id): void
    {
        $this->resetErrorBag();
        $m = Municipio::query()->findOrFail($id);
        $this->authorize('update', $m);
        $this->editingId = $m->id;
        $this->razao_social = $m->razao_social;
        $this->cnpj = $m->cnpj ?? '';
        $this->ie = $m->ie ?? '';
        $this->phone = $m->phone ?? '';
        $this->zipcode = $m->zipcode ?? '';
        $this->address = $m->address ?? '';
        $this->number = $m->number ?? '';
        $this->district = $m->district ?? '';
        $this->city = $m->city ?? '';
        $this->state = $m->state ?? '';
        $this->active = (bool) $m->active;
        $this->dispatchContactRamal = $m->dispatch_contact_ramal ?? '';
        $this->dispatchContactPhone = $m->dispatch_contact_phone ?? '';
        $this->dispatchContactWhatsapp = $m->dispatch_contact_whatsapp ?? '';
    }

    public function save(): void
    {
        $this->resetErrorBag();

        $validated = $this->validate([
            'razao_social' => [
                'required', 'string', 'max:255',
                Rule::unique('municipios', 'razao_social')->ignore($this->editingId)->whereNull('deleted_at'),
            ],
            'cnpj' => [
                'nullable', 'string', 'max:32',
                Rule::unique('municipios', 'cnpj')->ignore($this->editingId)->whereNull('deleted_at'),
            ],
            'ie' => [
                'nullable', 'string', 'max:64',
                Rule::unique('municipios', 'ie')->ignore($this->editingId)->whereNull('deleted_at'),
            ],
            'phone' => [
                'nullable', 'string', 'max:64',
                Rule::unique('municipios', 'phone')->ignore($this->editingId)->whereNull('deleted_at'),
            ],
            'zipcode' => ['nullable', 'string', 'max:16'],
            'address' => ['nullable', 'string', 'max:255'],
            'number' => ['nullable', 'string', 'max:32'],
            'district' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:255'],
            'state' => ['nullable', 'string', 'max:4'],
            'active' => ['boolean'],
            'dispatchContactRamal' => ['nullable', 'string', 'max:32'],
            'dispatchContactPhone' => ['nullable', 'string', 'max:32'],
            'dispatchContactWhatsapp' => ['nullable', 'string', 'max:32'],
        ]);

        $city = $validated['city'] ?: null;
        $state = $validated['state'] ?: null;

        $ibgeId = null;
        if ($city && $state) {
            $ibge = app(MunicipioGeoreferenceService::class)->find($city, $state);
            $ibgeId = $ibge?->id;
        }

        $payload = [
            'razao_social' => $validated['razao_social'],
            'cnpj' => $validated['cnpj'] ?: null,
            'ie' => $validated['ie'] ?: null,
            'phone' => $validated['phone'] ?: null,
            'zipcode' => $validated['zipcode'] ?: null,
            'address' => $validated['address'] ?: null,
            'number' => $validated['number'] ?: null,
            'district' => $validated['district'] ?: null,
            'city' => $city,
            'state' => $state,
            'active' => $validated['active'],
            'ibge_municipio_id' => $ibgeId,
            'dispatch_contact_ramal' => $validated['dispatchContactRamal'] ?: null,
            'dispatch_contact_phone' => $validated['dispatchContactPhone'] ?: null,
            'dispatch_contact_whatsapp' => $validated['dispatchContactWhatsapp'] ?: null,
        ];

        if ($this->editingId !== null) {
            $m = Municipio::query()->findOrFail($this->editingId);
            $this->authorize('update', $m);
            $m->update($payload);
            $this->message = __('Base atualizada.');
        } else {
            $this->authorize('create', Municipio::class);
            Municipio::query()->create($payload);
            $this->message = __('Base criada.');
        }

        $this->resetForm();
    }

    public function delete(int $id): void
    {
        $this->resetErrorBag();
        $m = Municipio::query()->findOrFail($id);
        $this->authorize('delete', $m);

        if ($this->baseHasOperationalLinks($m)) {
            $this->addError('delete', __('Não é possível excluir: existem vínculos operacionais (usuários, efetivo, viaturas, ocorrências ou turnos).'));

            return;
        }

        $m->delete();
        $this->message = __('Base excluída.');
        if ($this->editingId === $id) {
            $this->resetForm();
        }
    }

    public function render(): View
    {
        return view('livewire.operations.cadastro.municipio-manage', [
            'bases' => Municipio::query()->with('ibgeMunicipio')->orderBy('razao_social')->get(),
        ]);
    }

    private function baseHasOperationalLinks(Municipio $m): bool
    {
        return $m->users()->exists()
            || $m->staff()->exists()
            || $m->vehicles()->exists()
            || $m->incidents()->exists()
            || $m->shifts()->exists()
            || $m->protectedAreas()->exists();
    }
}
