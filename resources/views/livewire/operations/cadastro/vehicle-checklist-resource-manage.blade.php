<div class="cco-page-gap">
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <flux:heading size="xl">{{ __('Recursos do check-list') }}</flux:heading>
            <flux:text class="mt-1">{{ __('Cadastro de itens e unidades de medida por base, usados no check-list de viatura (ex.: Água — litros).') }}</flux:text>
        </div>
        <div class="flex flex-wrap gap-2">
            <flux:button variant="ghost" icon="clock" :href="route('operations.cadastro.shifts')" wire:navigate>{{ __('Turnos') }}</flux:button>
            <flux:button variant="ghost" icon="radio" :href="route('operations.dispatch')" wire:navigate>{{ __('CCO') }}</flux:button>
        </div>
    </div>

    @if (auth()->user()?->isOperationalCentral())
        <flux:card class="space-y-3">
            <flux:subheading>{{ __('Base (município)') }}</flux:subheading>
            <flux:select wire:model.live="selectedOperationalMunicipioId" :label="__('Base')" placeholder="{{ __('Selecione a base') }}">
                <flux:select.option value="">{{ __('—') }}</flux:select.option>
                @foreach ($operationalMunicipios as $municipio)
                    <flux:select.option value="{{ $municipio->id }}">{{ $municipio->razao_social }}</flux:select.option>
                @endforeach
            </flux:select>
        </flux:card>
    @endif

    @if ($scopeMunicipioId === null)
        <flux:callout variant="warning">{{ __('Selecione a base para cadastrar recursos.') }}</flux:callout>
    @endif

    @error('scope')
        <flux:callout variant="danger">{{ $message }}</flux:callout>
    @enderror

    @if ($message)
        <flux:callout variant="success">{{ $message }}</flux:callout>
    @endif

    @can('create', App\Models\VehicleChecklistResource::class)
        @if ($scopeMunicipioId !== null)
            <flux:card class="space-y-4">
                <flux:subheading>{{ $editingId ? __('Editar recurso') : __('Novo recurso') }}</flux:subheading>
                <form wire:submit="save" class="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
                    <flux:input wire:model="formName" :label="__('Item')" placeholder="{{ __('Ex.: Água') }}" />
                    <flux:input wire:model="formUnitOfMeasure" :label="__('Unidade de medida')" placeholder="{{ __('Ex.: litros') }}" />
                    <div class="flex flex-wrap items-end gap-2 md:col-span-2 lg:col-span-3">
                        <flux:button type="submit" variant="primary">{{ $editingId ? __('Salvar') : __('Incluir') }}</flux:button>
                        @if ($editingId)
                            <flux:button type="button" variant="ghost" wire:click="resetForm">{{ __('Cancelar') }}</flux:button>
                        @endif
                    </div>
                </form>
            </flux:card>
        @endif
    @endcan

    <flux:card class="space-y-4">
        <flux:subheading>{{ __('Itens cadastrados') }}</flux:subheading>
        <div class="cco-table-shell">
            <table class="min-w-full divide-y divide-slate-200 text-start text-sm dark:divide-slate-800/80">
                <thead>
                    <tr>
                        <th class="px-4 py-3 font-medium">{{ __('Item') }}</th>
                        <th class="px-4 py-3 font-medium">{{ __('Unidade') }}</th>
                        <th class="px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200 dark:divide-slate-800/80">
                    @forelse ($items as $item)
                        <tr wire:key="resource-row-{{ $item->id }}">
                            <td class="px-4 py-3 font-medium text-slate-800 dark:text-slate-100">{{ $item->name }}</td>
                            <td class="px-4 py-3 text-slate-600 dark:text-slate-400">{{ $item->unit_of_measure }}</td>
                            <td class="px-4 py-3 text-end">
                                @can('update', $item)
                                    <div class="flex items-center justify-end gap-1">
                                        <x-crud-icon-edit :item-id="$item->id" />
                                        @can('delete', $item)
                                            <x-crud-icon-delete :item-id="$item->id" :confirm-message="__('Excluir este recurso?')" />
                                        @endcan
                                    </div>
                                @endcan
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="3" class="px-4 py-6 text-center text-slate-500">
                                {{ $scopeMunicipioId === null ? __('Selecione a base.') : __('Nenhum recurso cadastrado nesta base.') }}
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </flux:card>
</div>
