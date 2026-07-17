<div class="cco-page-gap">
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <flux:heading size="xl">{{ __('Naturezas') }}</flux:heading>
            <flux:text class="mt-1">{{ __('Tipos e naturezas — cadastro global (somente operador central).') }}</flux:text>
        </div>
        <flux:button variant="ghost" icon="radio" :href="route('operations.dispatch')" wire:navigate>{{ __('CCO') }}</flux:button>
    </div>

    @if ($message)
        <flux:callout variant="success">{{ $message }}</flux:callout>
    @endif

    @error('typeDelete')
        <flux:callout variant="danger">{{ $message }}</flux:callout>
    @enderror

    <flux:card class="space-y-4">
        <flux:subheading>{{ __('Tipos de natureza') }}</flux:subheading>

        <form wire:submit="saveNatureType" class="flex flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-end">
            <flux:input wire:model="typeFormName" :label="__('Nome do tipo')" class="min-w-[14rem] flex-1" />
            <flux:button type="submit" variant="primary">{{ $editingTypeId ? __('Salvar tipo') : __('Incluir tipo') }}</flux:button>
            @if ($editingTypeId)
                <flux:button type="button" variant="ghost" wire:click="resetNatureTypeForm">{{ __('Cancelar') }}</flux:button>
            @endif
        </form>

        <div class="overflow-x-auto rounded-xl border border-zinc-200 dark:border-zinc-700">
            <table class="min-w-full divide-y divide-zinc-200 text-start text-sm dark:divide-zinc-700">
                <thead class="bg-zinc-50 dark:bg-zinc-900/50">
                    <tr>
                        <th class="px-4 py-3 font-medium">{{ __('Nome') }}</th>
                        <th class="px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-100 bg-white dark:divide-zinc-800 dark:bg-zinc-900">
                    @forelse ($natureTypes as $type)
                        <tr wire:key="nt-{{ $type->id }}">
                            <td class="px-4 py-3">{{ $type->name }}</td>
                            <td class="px-4 py-3 text-end">
                                <div class="flex items-center justify-end gap-1">
                                    <x-crud-icon-edit :item-id="$type->id" method="editNatureType" />
                                    <x-crud-icon-delete :item-id="$type->id" method="deleteNatureType" :confirm-message="__('Excluir este tipo?')" />
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="2" class="px-4 py-6 text-zinc-500">{{ __('Nenhum tipo.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </flux:card>

    <flux:card class="space-y-4">
        <flux:subheading>{{ __('Naturezas') }}</flux:subheading>

        <form wire:submit="saveNature" class="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
            <flux:input wire:model="natureFormName" :label="__('Nome')" />
            <flux:select wire:model="natureFormNatureTypeId" :label="__('Tipo')" placeholder="{{ __('Selecione') }}">
                @foreach ($natureTypes as $type)
                    <flux:select.option value="{{ $type->id }}">{{ $type->name }}</flux:select.option>
                @endforeach
            </flux:select>
            <flux:select wire:model="natureFormReportModality" :label="__('Modalidade de relatório')" placeholder="{{ __('— Nenhuma —') }}">
                @foreach ($reportModalities as $m)
                    <flux:select.option value="{{ $m->value }}">{{ $m->label() }}</flux:select.option>
                @endforeach
            </flux:select>
            <div class="flex flex-wrap items-end gap-2 md:col-span-2 lg:col-span-3">
                <flux:button type="submit" variant="primary">{{ $editingNatureId ? __('Salvar natureza') : __('Incluir natureza') }}</flux:button>
                @if ($editingNatureId)
                    <flux:button type="button" variant="ghost" wire:click="resetNatureForm">{{ __('Cancelar') }}</flux:button>
                @endif
            </div>
        </form>

        <div class="overflow-x-auto rounded-xl border border-zinc-200 dark:border-zinc-700">
            <table class="min-w-full divide-y divide-zinc-200 text-start text-sm dark:divide-zinc-700">
                <thead class="bg-zinc-50 dark:bg-zinc-900/50">
                    <tr>
                        <th class="px-4 py-3 font-medium">{{ __('Nome') }}</th>
                        <th class="px-4 py-3 font-medium">{{ __('Tipo') }}</th>
                        <th class="px-4 py-3 font-medium">{{ __('Modalidade') }}</th>
                        <th class="px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-100 bg-white dark:divide-zinc-800 dark:bg-zinc-900">
                    @forelse ($natures as $nature)
                        <tr wire:key="n-{{ $nature->id }}">
                            <td class="px-4 py-3 font-medium">{{ $nature->name }}</td>
                            <td class="px-4 py-3 text-zinc-600">{{ $nature->natureType?->name ?? '—' }}</td>
                            <td class="px-4 py-3 text-zinc-600">{{ $nature->report_modality?->label() ?? '—' }}</td>
                            <td class="px-4 py-3 text-end">
                                <div class="flex items-center justify-end gap-1">
                                    <x-crud-icon-edit :item-id="$nature->id" method="editNature" />
                                    <x-crud-icon-delete :item-id="$nature->id" method="deleteNature" :confirm-message="__('Excluir esta natureza?')" />
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="px-4 py-6 text-zinc-500">{{ __('Nenhuma natureza.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </flux:card>
</div>
