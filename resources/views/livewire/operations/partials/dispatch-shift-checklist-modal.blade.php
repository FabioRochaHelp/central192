<flux:modal wire:model.self="showShiftChecklistModal" wire:close="closeShiftChecklistModal" variant="floating" class="max-w-lg">
    @if ($shiftChecklistShift !== null)
        <div class="space-y-4">
            <div>
                <flux:heading size="lg">{{ __('Check-list da viatura') }}</flux:heading>
                <flux:text class="mt-1 text-slate-600 dark:text-slate-400">
                    {{ $shiftChecklistShift->vehicle?->prefix ?? __('Sem prefixo') }}
                    · {{ __('Turno') }} #{{ $shiftChecklistShift->id }}
                </flux:text>
            </div>

            @if ($shiftChecklistShift->isChecklistComplete())
                <flux:badge color="green">{{ __('Check-list concluído') }}</flux:badge>
            @else
                <flux:badge color="amber">{{ __('Check-list pendente') }}</flux:badge>
            @endif

            @if ($shiftChecklistShift->checklist_observation)
                <flux:callout variant="warning">
                    <flux:text class="font-medium">{{ __('Observação') }}</flux:text>
                    <flux:text class="mt-1">{{ $shiftChecklistShift->checklist_observation }}</flux:text>
                </flux:callout>
            @endif

            @if ($shiftChecklistShift->checklistItems->isEmpty())
                <flux:callout variant="info">{{ __('Nenhum recurso vinculado a esta viatura neste turno.') }}</flux:callout>
            @else
                <div class="overflow-x-auto rounded-xl border border-slate-200 dark:border-slate-700">
                    <table class="min-w-full divide-y divide-slate-200 text-sm dark:divide-slate-700">
                        <thead class="bg-slate-50 dark:bg-slate-900/50">
                            <tr>
                                <th class="px-3 py-2 text-start font-medium">{{ __('Item') }}</th>
                                <th class="px-3 py-2 text-start font-medium">{{ __('Unidade') }}</th>
                                <th class="px-3 py-2 text-end font-medium">{{ __('Qtd.') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                            @foreach ($shiftChecklistShift->checklistItems->sortBy(fn ($item) => $item->resource?->name) as $item)
                                <tr wire:key="dispatch-checklist-{{ $shiftChecklistShift->id }}-{{ $item->id }}">
                                    <td class="px-3 py-2 font-medium">{{ $item->resource?->name ?? '—' }}</td>
                                    <td class="px-3 py-2 text-slate-600 dark:text-slate-400">{{ $item->resource?->unit_of_measure ?? '—' }}</td>
                                    <td class="px-3 py-2 text-end tabular-nums">
                                        {{ number_format((int) $item->quantity, 0, ',', '.') }}
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif

            <div class="flex justify-end">
                <flux:button variant="ghost" wire:click="closeShiftChecklistModal">{{ __('Fechar') }}</flux:button>
            </div>
        </div>
    @endif
</flux:modal>
