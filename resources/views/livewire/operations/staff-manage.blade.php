<div class="cco-page-gap">
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <flux:heading size="xl">{{ __('Efetivo') }}</flux:heading>
            <flux:text class="mt-1">{{ __('Equipe operacional (documentos, contato, cargo legado; 2 = médico prescrição na doc).') }}</flux:text>
        </div>
        <flux:button variant="ghost" icon="radio" :href="route('operations.dispatch')" wire:navigate>{{ __('CCO') }}</flux:button>
    </div>

    @if (auth()->user()?->isOperationalCentral())
        <flux:card class="space-y-3">
            <flux:subheading>{{ __('Base (município)') }}</flux:subheading>
            <flux:text class="text-zinc-600">{{ __('O efetivo é gravado com o `municipio_id` da base escolhida.') }}</flux:text>
            <flux:select wire:model.live="selectedOperationalMunicipioId" :label="__('Base')" placeholder="{{ __('Selecione a base') }}">
                <flux:select.option value="">{{ __('—') }}</flux:select.option>
                @foreach ($operationalMunicipios as $municipio)
                    <flux:select.option value="{{ $municipio->id }}">{{ $municipio->razao_social }}</flux:select.option>
                @endforeach
            </flux:select>
        </flux:card>
    @endif

    @if ($scopeMunicipioId === null)
        <flux:callout variant="warning">{{ __('Selecione a base para listar e cadastrar efetivo neste município.') }}</flux:callout>
    @endif

    @error('scope')
        <flux:callout variant="danger">{{ $message }}</flux:callout>
    @enderror

    @if ($message)
        <flux:callout variant="success">{{ $message }}</flux:callout>
    @endif

    @can('create', App\Models\Staff::class)
        <flux:card class="space-y-4">
            <flux:subheading>{{ $editingId ? __('Editar registro') : __('Novo efetivo') }}</flux:subheading>
            <form wire:submit="save"
                  x-data="staffForm()"
                  class="grid gap-4 md:grid-cols-2 lg:grid-cols-3">

                <div class="md:col-span-2">
                    <flux:input wire:model="name" :label="__('Nome')" />
                    @error('name')
                        <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                    @enderror
                </div>

                <flux:select wire:model="cargo" :label="__('Cargo')" placeholder="{{ __('Selecione') }}">
                    <flux:select.option value="">{{ __('—') }}</flux:select.option>
                    @foreach ($cargoCases as $case)
                        <flux:select.option value="{{ $case->value }}">{{ $case->label() }}</flux:select.option>
                    @endforeach
                </flux:select>

                <flux:select wire:model="document_type" :label="__('Tipo documento')" placeholder="{{ __('Selecione') }}">
                    <flux:select.option value="">{{ __('—') }}</flux:select.option>
                    @foreach ($documentTypeCases as $case)
                        <flux:select.option value="{{ $case->value }}">{{ $case->label() }}</flux:select.option>
                    @endforeach
                </flux:select>
                <flux:input wire:model="document_number" :label="__('Nº documento')" />

                {{-- CPF com máscara, validação de formato e verificação de duplicidade --}}
                <div>
                    <div class="relative">
                        <flux:input
                            wire:model.blur="cpf"
                            wire:blur="checkDuplicateCpf"
                            :label="__('CPF')"
                            maxlength="14"
                            x-on:input="maskCpf($event)"
                            x-on:blur="validateCpfBlur($event)"
                            placeholder="XXX.XXX.XXX-XX"
                        />
                        <div wire:loading wire:target="checkDuplicateCpf"
                             class="absolute right-3 top-8 flex items-center">
                            <svg class="h-4 w-4 animate-spin text-zinc-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path>
                            </svg>
                        </div>
                    </div>
                    <p x-show="cpfError" x-text="cpfError" class="mt-1 text-sm text-red-600 dark:text-red-400"></p>
                    @if ($cpfTaken)
                        <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ __('Este CPF já está cadastrado') }}</p>
                    @endif
                    @error('cpf')
                        <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                    @enderror
                </div>

                {{-- E-mail com validação de formato --}}
                <div>
                    <flux:input
                        wire:model="email"
                        type="email"
                        :label="__('E-mail')"
                        x-on:blur="validateEmail($event.target.value)"
                        x-on:input="if (emailError) validateEmail($event.target.value)"
                    />
                    <p x-show="emailError" x-text="emailError" class="mt-1 text-sm text-red-600 dark:text-red-400"></p>
                    @error('email')
                        <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                    @enderror
                </div>

                {{-- Telefone com máscara --}}
                <div>
                    <flux:input
                        wire:model.blur="phone"
                        :label="__('Telefone')"
                        maxlength="15"
                        x-on:input="maskPhone($event)"
                        x-on:blur="validatePhone($event.target.value)"
                        placeholder="(XX) XXXXX-XXXX"
                    />
                    <p x-show="phoneError" x-text="phoneError" class="mt-1 text-sm text-red-600 dark:text-red-400"></p>
                    @error('phone')
                        <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                    @enderror
                </div>

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
        <flux:subheading>{{ __('Efetivo cadastrado') }}</flux:subheading>
        <div class="overflow-x-auto rounded-xl border border-zinc-200 dark:border-zinc-700">
            <table class="min-w-full divide-y divide-zinc-200 text-start text-sm dark:divide-zinc-700">
                <thead class="bg-zinc-50 dark:bg-zinc-900/50">
                    <tr>
                        <th class="px-4 py-3 font-medium">{{ __('Nome') }}</th>
                        <th class="px-4 py-3 font-medium">{{ __('Cargo') }}</th>
                        <th class="px-4 py-3 font-medium">{{ __('CPF') }}</th>
                        <th class="px-4 py-3 font-medium">{{ __('Contato') }}</th>
                        <th class="px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-100 bg-white dark:divide-zinc-800 dark:bg-zinc-900">
                    @forelse ($staffMembers as $s)
                        <tr wire:key="s-{{ $s->id }}">
                            <td class="px-4 py-3 font-medium">{{ $s->name }}</td>
                            <td class="px-4 py-3">{{ $s->cargo?->label() ?? '—' }}</td>
                            <td class="px-4 py-3 font-mono text-xs">{{ $s->cpf ?? '—' }}</td>
                            <td class="px-4 py-3 text-sm text-zinc-600">{{ $s->phone ?? $s->email ?? '—' }}</td>
                            <td class="px-4 py-3 text-end">
                                <div class="flex items-center justify-end gap-1">
                                    @can('update', $s)
                                        <x-crud-icon-edit :item-id="$s->id" />
                                    @endcan
                                    @can('delete', $s)
                                        <x-crud-icon-delete :item-id="$s->id" :confirm-message="__('Excluir este registro?')" />
                                    @endcan
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="px-4 py-6 text-zinc-500">{{ __('Nenhum registro neste escopo.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </flux:card>
</div>
