{{-- Tempo real via Reverb. wire:poll longo = fallback se o WebSocket cair. --}}
<div class="cco-page-gap !gap-6" wire:poll.120s>
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <flux:heading size="xl">{{ __('Ocorrências') }}</flux:heading>
            <flux:text class="mt-1">{{ __('Listas por status: em campo, pendência de relatório de enfermagem e encerradas definitivas.') }}</flux:text>
        </div>
        <div class="flex flex-wrap gap-2">
            @if (auth()->user()?->hasOperationalAbility('incident.create'))
                <flux:button variant="primary" icon="plus-circle" :href="route('operations.incidents.start')" wire:navigate>
                    {{ __('Nova ocorrência') }}
                </flux:button>
            @endif
            <flux:button variant="ghost" icon="radio" :href="route('operations.dispatch')" wire:navigate>
                {{ __('Ir para a central (CCO)') }}
            </flux:button>
        </div>
    </div>

    <flux:card class="space-y-4">
        <div class="flex flex-wrap gap-2">
            @foreach ([
                ['key' => 'open', 'label' => __('Abertas'), 'count' => $counts['open']],
                ['key' => 'field', 'label' => __('Em campo'), 'count' => $counts['field']],
                ['key' => 'pending_nurse', 'label' => __('Pend. enfermagem'), 'count' => $counts['pending_nurse']],
                ['key' => 'pending_final', 'label' => __('Pend. relatório CB'), 'count' => $counts['pending_final']],
                ['key' => 'qta', 'label' => __('QTA'), 'count' => $counts['qta']],
                ['key' => 'closed', 'label' => __('Encerradas'), 'count' => $counts['closed']],
                ['key' => 'cancelled', 'label' => __('Canceladas'),  'count' => $counts['cancelled']],
                ['key' => 'all',       'label' => __('Todas'),        'count' => $counts['all']],
                ['key' => 'logged',    'label' => __('Registradas'),  'count' => $counts['logged']],
            ] as $tab)
                <flux:button
                    size="sm"
                    :variant="$filter === $tab['key'] ? 'primary' : 'ghost'"
                    wire:click="setFilter('{{ $tab['key'] }}')"
                    wire:key="filter-{{ $tab['key'] }}"
                >
                    {{ $tab['label'] }}
                    <span class="ms-1 tabular-nums text-zinc-500">({{ $tab['count'] }})</span>
                </flux:button>
            @endforeach
        </div>

        @if ($incidents->isEmpty())
            <flux:text>{{ __('Nenhuma ocorrência neste filtro.') }}</flux:text>
        @else
            <div class="overflow-x-auto rounded-xl border border-zinc-200 dark:border-zinc-700">
                <table class="min-w-full divide-y divide-zinc-200 text-start text-sm dark:divide-zinc-700">
                    <thead class="bg-zinc-50 dark:bg-zinc-900/50">
                        <tr>
                            <th class="px-4 py-3 font-medium text-zinc-700 dark:text-zinc-300">{{ __('Talão') }}</th>
                            <th class="px-4 py-3 font-medium text-zinc-700 dark:text-zinc-300">{{ __('Status') }}</th>
                            <th class="px-4 py-3 font-medium text-zinc-700 dark:text-zinc-300">{{ __('Quando') }}</th>
                            <th class="px-4 py-3 font-medium text-zinc-700 dark:text-zinc-300">{{ __('Local') }}</th>
                            <th class="px-4 py-3 font-medium text-zinc-700 dark:text-zinc-300">{{ __('Natureza') }}</th>
                            <th class="px-4 py-3 font-medium text-zinc-700 dark:text-zinc-300">{{ __('Base') }}</th>
                            <th class="px-4 py-3"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-100 bg-white dark:divide-zinc-800 dark:bg-zinc-900">
                        @foreach ($incidents as $incident)
                            <tr wire:key="inc-{{ $incident->id }}" class="hover:bg-zinc-50/80 dark:hover:bg-zinc-800/40">
                                <td class="whitespace-nowrap px-4 py-3 font-semibold tabular-nums">{{ $incident->talao }}/{{ $incident->dispatch_year }}</td>
                                <td class="px-4 py-3">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <x-incident.status-badge :status="$incident->status" />
                                        <x-incident.manchester-badge :risk="$incident->manchester_risk" :showPrefix="false" />
                                    </div>
                                </td>
                                <td class="whitespace-nowrap px-4 py-3 text-zinc-600 dark:text-zinc-400">{{ $incident->occurred_at->format('d/m/Y H:i') }}</td>
                                <td class="max-w-[14rem] px-4 py-3 text-zinc-700 dark:text-zinc-300">
                                    {{ $incident->address_line ?? '—' }}
                                    @if ($incident->city)
                                        <span class="block text-xs text-zinc-500">{{ $incident->city }}</span>
                                    @endif
                                </td>
                                <td class="max-w-[12rem] truncate px-4 py-3">{{ $incident->nature?->name ?? '—' }}</td>
                                <td class="px-4 py-3 text-xs text-zinc-500">{{ $incident->municipio?->razao_social ?? ('#'.$incident->municipio_id) }}</td>
                                <td class="px-4 py-3 text-end">
                                    @can('view', $incident)
                                        <flux:button size="sm" variant="ghost" :href="route('operations.incidents.show', $incident)" wire:navigate>
                                            {{ __('Detalhe') }}
                                        </flux:button>
                                    @endcan
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="mt-2">
                {{ $incidents->links() }}
            </div>
        @endif
    </flux:card>
</div>
