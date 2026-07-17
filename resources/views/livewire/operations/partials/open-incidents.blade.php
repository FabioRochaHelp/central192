<flux:card class="space-y-4">
    <div class="flex flex-wrap items-center justify-between gap-2">
        <flux:subheading>{{ __('Ocorrências — fila de despacho') }}</flux:subheading>
        <div class="flex flex-wrap items-center gap-2 text-xs font-medium">
            <span class="inline-flex items-center gap-1 rounded-full border border-red-600 bg-red-600 px-2 py-0.5 text-white">{{ __('U') }} {{ __('Urgente') }}</span>
            <span class="inline-flex items-center gap-1 rounded-full border border-orange-600 bg-orange-500 px-2 py-0.5 text-white">{{ __('L') }} {{ __('Alerta') }}</span>
            <span class="inline-flex items-center gap-1 rounded-full border border-blue-600 bg-blue-600 px-2 py-0.5 text-white">{{ __('N') }} {{ __('Normal') }}</span>
        </div>
    </div>

    @if ($openIncidents->isEmpty())
        <flux:text>{{ __('Nenhuma ocorrência aguardando empenho.') }}</flux:text>
    @else
        <div class="cco-table-shell overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200/95 text-start text-sm dark:divide-slate-800/80">
                <thead>
                    <tr>
                        <th class="px-4 py-3 font-medium">{{ __('Prior.') }}</th>
                        <th class="px-4 py-3 font-medium">{{ __('Talão') }}</th>
                        <th class="px-4 py-3 font-medium">{{ __('Quando') }}</th>
                        <th class="px-4 py-3 font-medium">{{ __('Endereço') }}</th>
                        <th class="px-4 py-3 font-medium">{{ __('Natureza') }}</th>
                        <th class="px-4 py-3 font-medium">{{ __('Risco') }}</th>
                        <th class="px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200/95 dark:divide-slate-800/80">
                    @foreach ($openIncidents as $incident)
                        @php
                            $canDispatch = auth()->user()?->can('dispatchUnit', $incident);
                            $canCancel = auth()->user()?->can('cancel', $incident);
                            $canObserve = auth()->user()?->can('addObservation', $incident);
                            $canView = auth()->user()?->can('view', $incident);

                            $callType = \App\Domain\Operations\Enums\CallType::tryFrom((string) $incident->patient_call_type);
                            $accent = $callType?->dispatchQueueAccentClasses() ?? \App\Domain\Operations\Enums\CallType::Normal->dispatchQueueAccentClasses();
                            $callTypeInitial = $incident->patient_call_type ?: '—';
                            $callTypeLabel = $callType?->label() ?? __('Sem classificação');
                        @endphp
                        <tr
                            wire:key="open-{{ $incident->id }}"
                            @if ($canDispatch)
                                wire:click="openDispatchModal({{ $incident->id }})"
                                class="cursor-pointer transition-colors hover:brightness-[0.98] dark:hover:brightness-110 {{ $accent['row'] }}"
                            @else
                                class="transition-colors {{ $accent['row'] }}"
                            @endif
                        >
                            <td class="whitespace-nowrap px-4 py-3">
                                <span
                                    class="inline-flex h-8 min-w-8 items-center justify-center rounded-lg border px-2 font-mono text-sm font-bold {{ $accent['badge'] }}"
                                    title="{{ $callTypeLabel }}"
                                >
                                    {{ $callTypeInitial }}
                                </span>
                            </td>
                            <td class="whitespace-nowrap px-4 py-3">
                                <div class="flex items-center gap-2">
                                    <span class="h-6 w-1 rounded-full {{ $accent['bar'] }}" aria-hidden="true"></span>
                                    <span class="font-semibold tabular-nums text-slate-900 dark:text-slate-100">{{ $incident->talao }}/{{ $incident->dispatch_year }}</span>
                                    @if (($incident->call_requests_count ?? 0) > 0)
                                        @php $totalRequests = $incident->totalCallRequestCount(); @endphp
                                        <span
                                            class="inline-flex h-6 min-w-6 items-center justify-center rounded-full border border-amber-400 bg-amber-100 px-1.5 text-xs font-bold tabular-nums text-amber-900 dark:border-amber-600/70 dark:bg-amber-950/60 dark:text-amber-200"
                                            title="{{ trans_choice('{1} :count solicitação para este ponto|[2,*] :count solicitações para este ponto', $totalRequests) }}"
                                        >
                                            {{ $totalRequests }}
                                        </span>
                                    @endif
                                </div>
                            </td>
                            <td class="whitespace-nowrap px-4 py-3 text-slate-600 dark:text-slate-400">{{ $incident->occurred_at->format('d/m/Y H:i') }}</td>
                            <td class="max-w-[18rem] truncate px-4 py-3 text-slate-700 dark:text-slate-300">{{ $incident->address_line ?? '—' }}</td>
                            <td class="max-w-[14rem] px-4 py-3 text-slate-700 dark:text-slate-300">
                                <div class="flex items-center gap-2">
                                    <span class="truncate">{{ $incident->nature?->name ?? '—' }}</span>
                                    @if ($incident->regulation?->recommended_resource)
                                        <flux:badge size="sm" color="indigo" title="{{ __('Recurso indicado na regulação') }}">
                                            {{ $incident->regulation->recommended_resource->value ? strtoupper($incident->regulation->recommended_resource->value) : '' }}
                                        </flux:badge>
                                    @endif
                                </div>
                            </td>
                            <td class="whitespace-nowrap px-4 py-3">
                                <x-incident.manchester-badge :risk="$incident->manchester_risk" :showPrefix="false" />
                            </td>
                            <td class="px-2 py-3" @click.stop>
                                <flux:dropdown>
                                    <flux:button icon="ellipsis-horizontal" variant="ghost" size="sm" />
                                    <flux:menu>
                                        @if ($canView)
                                            <flux:menu.item icon="eye" wire:click="openDetailModal({{ $incident->id }})">
                                                {{ __('Ver ocorrência') }}
                                            </flux:menu.item>
                                        @endif
                                        @if ($canObserve)
                                            <flux:menu.item icon="chat-bubble-left-ellipsis" wire:click="openObservationModal({{ $incident->id }})">
                                                {{ __('Adicionar à descrição') }}
                                            </flux:menu.item>
                                        @endif
                                        @if ($canCancel)
                                            <flux:menu.separator />
                                            <flux:menu.item icon="x-circle" variant="danger" wire:click="openCancelModal({{ $incident->id }})">
                                                {{ __('Cancelar ocorrência') }}
                                            </flux:menu.item>
                                        @endif
                                    </flux:menu>
                                </flux:dropdown>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</flux:card>
