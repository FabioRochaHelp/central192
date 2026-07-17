<?php

declare(strict_types=1);

namespace App\Livewire\Operations\Admin;

use App\Domain\Operations\Enums\UserLegacyProfile;
use App\Models\Municipio;
use App\Models\Staff;
use App\Models\User;
use App\Models\UserType;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Cadastro de usuários do sistema — apenas administrador central (docs/migracao/entidades.md).
 */
#[Layout('layouts.app')]
#[Title('Usuários do sistema')]
final class SystemUserManage extends Component
{
    public string $name = '';

    public string $email = '';

    public string $password = '';

    public string $password_confirmation = '';

    /** @var numeric-string|'' */
    public string $users_type_legacy = '';

    public ?string $user_type_id = null;

    /** @var numeric-string|'' */
    public string $municipio_id = '';

    public ?string $staff_id = null;

    public bool $active_operational = true;

    public ?int $editingId = null;

    public string $message = '';

    public function mount(): void
    {
        Gate::authorize('viewAny', User::class);
    }

    protected function prepareForValidation($attributes): array
    {
        if (($attributes['user_type_id'] ?? '') === '' || ($attributes['user_type_id'] ?? '') === '0') {
            $attributes['user_type_id'] = null;
        }
        if (($attributes['staff_id'] ?? '') === '') {
            $attributes['staff_id'] = null;
        }

        return $attributes;
    }

    public function updatedUsersTypeLegacy(?string $value): void
    {
        $profile = $value !== null && $value !== ''
            ? UserLegacyProfile::tryFrom((int) $value)
            : null;

        if ($profile === null || ! $profile->requiresMunicipio()) {
            $this->municipio_id = '';
            $this->staff_id = null;
        }
    }

    public function updatedMunicipioId(?string $value): void
    {
        if ($value === null || $value === '') {
            $this->staff_id = null;
        }
    }

    public function selectedProfileRequiresMunicipio(): bool
    {
        if ($this->users_type_legacy === '') {
            return false;
        }

        return UserLegacyProfile::tryFrom((int) $this->users_type_legacy)?->requiresMunicipio() ?? false;
    }

    /** @return array<int, string> */
    public static function legacyProfileLabels(): array
    {
        $labels = [];

        foreach (UserLegacyProfile::cases() as $profile) {
            $labels[$profile->value] = $profile->label();
        }

        return $labels;
    }

    public function resetForm(): void
    {
        $this->name = '';
        $this->email = '';
        $this->password = '';
        $this->password_confirmation = '';
        $this->users_type_legacy = '';
        $this->user_type_id = null;
        $this->municipio_id = '';
        $this->staff_id = null;
        $this->active_operational = true;
        $this->editingId = null;
    }

    public function edit(int $id): void
    {
        $this->resetErrorBag();
        $user = User::query()->findOrFail($id);
        $this->authorize('update', $user);
        $this->editingId = $user->id;
        $this->name = $user->name;
        $this->email = $user->email;
        $this->password = '';
        $this->password_confirmation = '';
        $this->users_type_legacy = $user->users_type_legacy !== null ? (string) $user->users_type_legacy : '';
        $this->user_type_id = $user->user_type_id !== null ? (string) $user->user_type_id : null;
        $this->municipio_id = $user->municipio_id !== null ? (string) $user->municipio_id : '';
        $this->staff_id = $user->staff_id !== null ? (string) $user->staff_id : null;
        $this->active_operational = (bool) $user->active_operational;
    }

    public function save(): void
    {
        $this->resetErrorBag();
        $actor = Auth::user();
        abort_unless($actor instanceof User, 403);

        $legacy = (int) $this->users_type_legacy;
        $profile = UserLegacyProfile::from($legacy);
        $passwordRules = $this->editingId === null
            ? ['required', 'string', 'min:8', 'confirmed']
            : ['nullable', 'string', 'min:8', 'confirmed'];

        $validated = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'string',
                'lowercase',
                'email',
                'max:255',
                Rule::unique('users', 'email')->ignore($this->editingId),
            ],
            'password' => $passwordRules,
            'users_type_legacy' => ['required', 'integer', Rule::in(UserLegacyProfile::values())],
            'user_type_id' => ['nullable', 'integer', 'exists:user_types,id'],
            'municipio_id' => [
                Rule::requiredIf($profile->requiresMunicipio()),
                'nullable',
                'integer',
                'exists:municipios,id',
            ],
            'staff_id' => ['nullable', 'integer', 'exists:staff,id'],
            'active_operational' => ['boolean'],
        ]);

        $municipioId = $profile->requiresMunicipio()
            ? (isset($validated['municipio_id']) ? (int) $validated['municipio_id'] : null)
            : null;
        if ($profile->requiresMunicipio() && $municipioId === null) {
            $this->addError('municipio_id', __('Selecione a base para perfis municipais.'));

            return;
        }

        $staffId = isset($validated['staff_id']) && $validated['staff_id'] !== null ? (int) $validated['staff_id'] : null;
        if ($staffId !== null && $staffId !== 0) {
            $ownsStaff = Staff::query()->whereKey($staffId)->where('municipio_id', $municipioId)->exists();
            if (! $profile->requiresMunicipio() || ! $ownsStaff) {
                $this->addError('staff_id', __('O efetivo deve pertencer à mesma base do usuário.'));

                return;
            }
        } else {
            $staffId = null;
        }

        $userTypeId = isset($validated['user_type_id']) && $validated['user_type_id'] !== null
            ? (int) $validated['user_type_id']
            : null;

        $payload = [
            'name' => $validated['name'],
            'email' => $validated['email'],
            'users_type_legacy' => $legacy,
            'user_type_id' => $userTypeId,
            'municipio_id' => $municipioId,
            'staff_id' => $profile->requiresMunicipio() ? $staffId : null,
            'active_operational' => (bool) $validated['active_operational'],
        ];

        if (($validated['password'] ?? '') !== '') {
            $payload['password'] = Hash::make($validated['password']);
        }

        if ($this->editingId !== null) {
            $user = User::query()->findOrFail($this->editingId);
            $this->authorize('update', $user);

            if ($user->id === $actor->id) {
                if ($legacy !== 1) {
                    $this->addError('users_type_legacy', __('Você não pode alterar seu próprio perfil administrativo.'));

                    return;
                }
                if (! $payload['active_operational']) {
                    $this->addError('active_operational', __('Você não pode desativar seu próprio acesso.'));

                    return;
                }
            }

            $user->update($payload);
            $this->message = __('Usuário atualizado.');
        } else {
            $this->authorize('create', User::class);
            User::query()->create($payload);
            $this->message = __('Usuário criado.');
        }

        $this->resetForm();
    }

    public function delete(int $id): void
    {
        $this->resetErrorBag();
        $user = User::query()->findOrFail($id);
        $this->authorize('delete', $user);
        $user->delete();
        $this->message = __('Usuário removido.');
        if ($this->editingId === $id) {
            $this->resetForm();
        }
    }

    public function render(): View
    {
        $users = User::query()
            ->with(['municipio', 'userType'])
            ->orderBy('name')
            ->limit(200)
            ->get();

        return view('livewire.operations.admin.system-user-manage', [
            'users' => $users,
            'legacyLabels' => self::legacyProfileLabels(),
            'userTypes' => UserType::query()->orderBy('name')->get(),
            'municipios' => Municipio::query()->where('active', true)->orderBy('razao_social')->get(),
            'staffMembers' => $this->staffOptions(),
        ]);
    }

    /** @return EloquentCollection<int, Staff> */
    private function staffOptions(): EloquentCollection
    {
        $legacy = (int) $this->users_type_legacy;
        $profile = UserLegacyProfile::tryFrom($legacy);
        if ($profile === null || ! $profile->requiresMunicipio() || $this->municipio_id === '') {
            return new EloquentCollection;
        }

        return Staff::query()
            ->where('municipio_id', (int) $this->municipio_id)
            ->orderBy('name')
            ->get();
    }
}
