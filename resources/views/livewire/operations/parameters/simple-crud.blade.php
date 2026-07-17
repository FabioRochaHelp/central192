<div class="cco-page-gap">
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <flux:heading size="xl">{{ $heading }}</flux:heading>
            <flux:text class="mt-1">{{ __('Cadastro global de parâmetro da ocorrência (somente operador central).') }}</flux:text>
        </div>
        <flux:button variant="ghost" icon="radio" :href="route('operations.dispatch')" wire:navigate>{{ __('CCO') }}</flux:button>
    </div>

    @if ($message)
        <flux:callout variant="success">{{ $message }}</flux:callout>
    @endif

    <flux:card class="space-y-4">
        <flux:subheading>{{ $editingId ? __('Editar') : __('Novo registro') }}</flux:subheading>
        <form wire:submit="save" class="flex flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-end">
            <flux:input wire:model="formName" :label="__('Nome')" class="min-w-[14rem] flex-1" />
            <flux:button type="submit" variant="primary">{{ $editingId ? __('Salvar') : __('Incluir') }}</flux:button>
            @if ($editingId)
                <flux:button type="button" variant="ghost" wire:click="resetForm">{{ __('Cancelar') }}</flux:button>
            @endif
        </form>
    </flux:card>

    <flux:card class="space-y-4">
        <flux:subheading>{{ __('Registros') }}</flux:subheading>
        <div class="overflow-x-auto rounded-xl border border-zinc-200 dark:border-zinc-700">
            <table class="min-w-full divide-y divide-zinc-200 text-start text-sm dark:divide-zinc-700">
                <thead class="bg-zinc-50 dark:bg-zinc-900/50">
                    <tr>
                        <th class="px-4 py-3 font-medium">{{ __('Nome') }}</th>
                        <th class="px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-100 bg-white dark:divide-zinc-800 dark:bg-zinc-900">
                    @forelse ($items as $item)
                        <tr wire:key="row-{{ $item->id }}">
                            <td class="px-4 py-3">{{ $item->name }}</td>
                            <td class="px-4 py-3 text-end">
                                <div class="flex items-center justify-end gap-1">
                                    <x-crud-icon-edit :item-id="$item->id" />
                                    <x-crud-icon-delete :item-id="$item->id" :confirm-message="__('Excluir este registro?')" />
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="2" class="px-4 py-6 text-zinc-500">{{ __('Nenhum registro.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </flux:card>
</div>
