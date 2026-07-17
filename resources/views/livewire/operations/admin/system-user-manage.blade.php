<div class="cco-page-gap">
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <flux:heading size="xl">{{ __('Usuários do sistema') }}</flux:heading>
            <flux:text class="mt-1">
                {{ __('Cadastro de contas conforme docs/migracao (usuário e tipo). Apenas o administrador da central pode gerir usuários.') }}
            </flux:text>
        </div>
        <flux:button variant="ghost" icon="home" :href="route('dashboard')" wire:navigate>{{ __('Painel') }}</flux:button>
    </div>

    @if ($message)
        <flux:callout variant="success">{{ $message }}</flux:callout>
    @endif

    @can('create', App\Models\User::class)
        <flux:card class="space-y-4">
            <flux:subheading>{{ $editingId ? __('Editar usuário') : __('Novo usuário') }}</flux:subheading>
            <form wire:submit="save" class="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
                <flux:input wire:model="name" :label="__('Nome')" type="text" autocomplete="name" class="md:col-span-2 lg:col-span-3" />
                <flux:input wire:model="email" :label="__('E-mail')" type="email" autocomplete="email" class="md:col-span-2 lg:col-span-3" />

                <flux:input wire:model="password" :label="$editingId ? __('Nova senha (opcional)') : __('Senha')" type="password" autocomplete="new-password" />
                <flux:input wire:model="password_confirmation" :label="__('Confirmar senha')" type="password" autocomplete="new-password" />

                <flux:select wire:model.live="users_type_legacy" :label="__('Perfil legado (acesso)')" placeholder="{{ __('Selecione') }}" class="md:col-span-2 lg:col-span-3">
                    <flux:select.option value="">{{ __('—') }}</flux:select.option>
                    @foreach ($legacyLabels as $value => $label)
                        <flux:select.option value="{{ $value }}">{{ $label }}</flux:select.option>
                    @endforeach
                </flux:select>

                <flux:select wire:model="user_type_id" :label="__('Tipo de usuário (cadastro)')" placeholder="{{ __('Opcional') }}">
                    <flux:select.option value="">{{ __('—') }}</flux:select.option>
                    @foreach ($userTypes as $ut)
                        <flux:select.option value="{{ $ut->id }}">{{ $ut->name }}</flux:select.option>
                    @endforeach
                </flux:select>

                <flux:select
                    wire:model.live="municipio_id"
                    :label="__('Base (município)')"
                    placeholder="{{ __('Obrigatório para perfil municipal') }}"
                    class="md:col-span-2"
                    :disabled="$users_type_legacy !== '' && ! $this->selectedProfileRequiresMunicipio()"
                >
                    <flux:select.option value="">{{ __('—') }}</flux:select.option>
                    @foreach ($municipios as $m)
                        <flux:select.option value="{{ $m->id }}">{{ $m->razao_social }}</flux:select.option>
                    @endforeach
                </flux:select>

                <flux:select
                    wire:model="staff_id"
                    :label="__('Vínculo com efetivo (opcional)')"
                    placeholder="{{ __('Mesma base') }}"
                    class="md:col-span-2 lg:col-span-3"
                    :disabled="! $this->selectedProfileRequiresMunicipio() || $municipio_id === '' || $municipio_id === null"
                >
                    <flux:select.option value="">{{ __('—') }}</flux:select.option>
                    @foreach ($staffMembers as $s)
                        <flux:select.option value="{{ $s->id }}">{{ $s->name }}</flux:select.option>
                    @endforeach
                </flux:select>

                <flux:switch wire:model="active_operational" :label="__('Acesso operacional ativo')" align="left" class="md:col-span-2 lg:col-span-3" />

                <div class="flex flex-wrap gap-2 md:col-span-2 lg:col-span-3">
                    <flux:button type="submit" variant="primary">{{ $editingId ? __('Salvar') : __('Incluir') }}</flux:button>
                    @if ($editingId)
                        <flux:button type="button" variant="ghost" wire:click="resetForm">{{ __('Cancelar') }}</flux:button>
                    @endif
                </div>
            </form>
        </flux:card>
    @endcan

    <flux:card class="space-y-4">
        <flux:subheading>{{ __('Usuários cadastrados') }}</flux:subheading>
        <div class="cco-table-shell overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200 text-start text-sm dark:divide-slate-800/80">
                <thead>
                    <tr>
                        <th class="px-4 py-3 font-medium">{{ __('Nome') }}</th>
                        <th class="px-4 py-3 font-medium">{{ __('E-mail') }}</th>
                        <th class="px-4 py-3 font-medium">{{ __('Perfil') }}</th>
                        <th class="px-4 py-3 font-medium">{{ __('Base') }}</th>
                        <th class="px-4 py-3 font-medium">{{ __('Ativo') }}</th>
                        <th class="px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200 dark:divide-slate-800/80">
                    @forelse ($users as $u)
                        <tr wire:key="user-row-{{ $u->id }}">
                            <td class="px-4 py-3 font-medium text-slate-800 dark:text-slate-100">{{ $u->name }}</td>
                            <td class="whitespace-nowrap px-4 py-3 text-slate-600 dark:text-slate-400">{{ $u->email }}</td>
                            <td class="px-4 py-3 text-slate-600 dark:text-slate-400">
                                {{ $legacyLabels[$u->users_type_legacy] ?? ('#'.$u->users_type_legacy) }}
                            </td>
                            <td class="px-4 py-3 text-xs text-slate-500">{{ $u->municipio?->razao_social ?? '—' }}</td>
                            <td class="px-4 py-3">
                                @if ($u->active_operational)
                                    <flux:badge color="green" size="sm" inset>{{ __('Sim') }}</flux:badge>
                                @else
                                    <flux:badge color="zinc" size="sm" inset>{{ __('Não') }}</flux:badge>
                                @endif
                            </td>
                            <td class="whitespace-nowrap px-4 py-3 text-end">
                                <div class="flex items-center justify-end gap-1">
                                    @can('update', $u)
                                        <x-crud-icon-edit :item-id="$u->id" />
                                    @endcan
                                    @can('delete', $u)
                                        <x-crud-icon-delete
                                            :item-id="$u->id"
                                            :confirm-message="__('Remover este usuário? Esta ação não pode ser desfeita.')"
                                        />
                                    @endcan
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-4 py-6 text-center text-slate-500">{{ __('Nenhum usuário.') }}</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </flux:card>
</div>
