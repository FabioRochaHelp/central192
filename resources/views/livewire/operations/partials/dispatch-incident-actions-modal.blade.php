{{-- Modal: Cancelar ocorrência --}}
<flux:modal wire:model.self="showCancelModal" wire:close="closeCancelModalOnly" class="max-w-md">
    <div class="space-y-4">
        <div>
            <flux:heading size="lg">{{ __('Cancelar ocorrência') }}</flux:heading>
            <flux:text class="mt-1 text-zinc-600 dark:text-zinc-400">
                {{ __('Informe o motivo do cancelamento. Esta ação é registrada no histórico.') }}
            </flux:text>
        </div>

        @error('cancelReason')
            <flux:callout variant="danger" class="text-sm">{{ $message }}</flux:callout>
        @enderror

        <flux:textarea
            wire:model="cancelReason"
            :label="__('Motivo')"
            rows="3"
            placeholder="{{ __('Descreva o motivo do cancelamento…') }}"
            autofocus
        />

        <div class="flex flex-wrap gap-2">
            <flux:button
                variant="danger"
                icon="x-circle"
                wire:click="cancelIncident"
                wire:loading.attr="disabled"
            >
                {{ __('Confirmar cancelamento') }}
            </flux:button>
            <flux:button variant="outline" wire:click="closeActionModals">
                {{ __('Voltar') }}
            </flux:button>
        </div>
    </div>
</flux:modal>

{{-- Modal: Adicionar à descrição --}}
<flux:modal wire:model.self="showObservationModal" wire:close="closeObservationModal" class="max-w-lg">
    <div class="space-y-4">
        <div>
            <flux:heading size="lg">{{ __('Adicionar à descrição') }}</flux:heading>
            <flux:text class="mt-1 text-zinc-600 dark:text-zinc-400">
                {{ __('O texto anterior é mantido. Cada anotação registra data, hora e operador.') }}
            </flux:text>
        </div>

        @if ($actionIncident?->description)
            <flux:callout class="border-s-blue-200 bg-blue-50/80 dark:border-blue-900/60 dark:bg-blue-950/30">
                <div class="text-xs font-semibold uppercase tracking-wide text-blue-800 dark:text-blue-200">
                    {{ __('Descrição atual') }}
                </div>
                <flux:text class="mt-2 max-h-40 overflow-y-auto whitespace-pre-wrap text-sm text-slate-800 dark:text-slate-100">
                    {{ $actionIncident->description }}
                </flux:text>
            </flux:callout>
        @endif

        @error('observationText')
            <flux:callout variant="danger" class="text-sm">{{ $message }}</flux:callout>
        @enderror

        <flux:textarea
            wire:model="observationText"
            :label="__('Nova anotação')"
            rows="4"
            placeholder="{{ __('Descreva a nova informação…') }}"
            autofocus
        />

        <div class="flex flex-wrap gap-2">
            <flux:button
                variant="primary"
                color="amber"
                icon="chat-bubble-left-ellipsis"
                wire:click="saveObservation"
                wire:loading.attr="disabled"
            >
                {{ __('Salvar na descrição') }}
            </flux:button>
            <flux:button variant="outline" wire:click="closeActionModals">
                {{ __('Cancelar') }}
            </flux:button>
        </div>
    </div>
</flux:modal>

{{-- Modal: Detalhe da ocorrência --}}
<flux:modal wire:model.self="showDetailModal" wire:close="closeDetailModal" variant="floating" class="max-w-3xl !p-0">
    @if ($actionIncident !== null)
        @php
            $callType = \App\Domain\Operations\Enums\CallType::tryFrom((string) $actionIncident->patient_call_type);
            $accent = $callType?->dispatchQueueAccentClasses() ?? \App\Domain\Operations\Enums\CallType::Normal->dispatchQueueAccentClasses();
            $callTypeInitial = $actionIncident->patient_call_type ?: '—';
            $callTypeLabel = $callType?->label() ?? __('Sem classificação');
            $canDispatch = auth()->user()?->can('dispatchUnit', $actionIncident);
            $canCancel = auth()->user()?->can('cancel', $actionIncident);
            $canObserve = auth()->user()?->can('addObservation', $actionIncident);
            $address = collect([$actionIncident->address_line, $actionIncident->city])->filter()->join(', ');
            $caller = collect([$actionIncident->caller_name, $actionIncident->caller_phone])->filter()->join(' · ');
        @endphp

        <div class="overflow-hidden rounded-2xl">
            <div class="border-b border-slate-200/90 px-6 py-5 dark:border-slate-700/80 {{ $accent['row'] }}">
                <div class="flex flex-wrap items-start justify-between gap-4">
                    <div class="flex min-w-0 items-start gap-3">
                        <span
                            class="inline-flex h-11 min-w-11 shrink-0 items-center justify-center rounded-xl border px-2 font-mono text-lg font-bold {{ $accent['badge'] }}"
                            title="{{ $callTypeLabel }}"
                        >
                            {{ $callTypeInitial }}
                        </span>
                        <div class="min-w-0">
                            <flux:heading size="lg" class="tabular-nums">
                                {{ __('Talão :talao/:ano', ['talao' => $actionIncident->talao, 'ano' => $actionIncident->dispatch_year]) }}
                            </flux:heading>
                            <flux:text class="mt-1 font-medium text-slate-700 dark:text-slate-200">
                                {{ $actionIncident->occurred_at?->format('d/m/Y H:i') ?? '—' }}
                                @if ($actionIncident->municipio?->razao_social)
                                    <span class="text-slate-500 dark:text-slate-400">· {{ $actionIncident->municipio->razao_social }}</span>
                                @endif
                            </flux:text>
                            <flux:text size="sm" class="mt-0.5 text-slate-600 dark:text-slate-400">{{ $callTypeLabel }}</flux:text>
                        </div>
                    </div>
                    <div class="flex flex-wrap items-center justify-end gap-2">
                        <x-incident.status-badge :status="$actionIncident->status" size="sm" />
                        <x-incident.manchester-badge :risk="$actionIncident->manchester_risk" :showPrefix="false" />
                    </div>
                </div>
            </div>

            <div class="space-y-5 bg-white p-6 dark:bg-slate-950">
                <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                    <div class="rounded-xl border border-slate-200/90 bg-slate-50/80 p-3 dark:border-slate-700 dark:bg-slate-900/50">
                        <div class="flex items-center gap-2 text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">
                            <flux:icon.bolt class="size-4 shrink-0" />
                            {{ __('Natureza') }}
                        </div>
                        <p class="mt-1.5 text-sm font-semibold text-slate-900 dark:text-slate-100">{{ $actionIncident->nature?->name ?? '—' }}</p>
                    </div>

                    <div class="rounded-xl border border-slate-200/90 bg-slate-50/80 p-3 dark:border-slate-700 dark:bg-slate-900/50">
                        <div class="flex items-center gap-2 text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">
                            <flux:icon.map-pin class="size-4 shrink-0" />
                            {{ __('Endereço') }}
                        </div>
                        <p class="mt-1.5 text-sm font-semibold text-slate-900 dark:text-slate-100">{{ $address ?: '—' }}</p>
                        @if ($actionIncident->reference_notes)
                            <p class="mt-1 text-xs text-slate-600 dark:text-slate-400">{{ $actionIncident->reference_notes }}</p>
                        @endif
                    </div>

                    @if ($caller !== '')
                        <div class="rounded-xl border border-slate-200/90 bg-slate-50/80 p-3 dark:border-slate-700 dark:bg-slate-900/50">
                            <div class="flex items-center gap-2 text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">
                                <flux:icon.phone class="size-4 shrink-0" />
                                {{ __('Solicitante') }}
                            </div>
                            <p class="mt-1.5 text-sm font-semibold text-slate-900 dark:text-slate-100">{{ $caller }}</p>
                        </div>
                    @endif

                    @if ($actionIncident->latitude && $actionIncident->longitude)
                        <div class="rounded-xl border border-slate-200/90 bg-slate-50/80 p-3 dark:border-slate-700 dark:bg-slate-900/50">
                            <div class="flex items-center gap-2 text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">
                                <flux:icon.globe-alt class="size-4 shrink-0" />
                                {{ __('Coordenadas') }}
                            </div>
                            <p class="mt-1.5 font-mono text-sm font-semibold text-slate-900 dark:text-slate-100">
                                {{ $actionIncident->latitude }}, {{ $actionIncident->longitude }}
                            </p>
                        </div>
                    @endif
                </div>

                @if ($actionIncident->description)
                    <flux:callout class="border-s-blue-200 bg-blue-50/80 dark:border-blue-900/60 dark:bg-blue-950/30">
                        <div class="flex items-center gap-2 text-xs font-semibold uppercase tracking-wide text-blue-800 dark:text-blue-200">
                            <flux:icon.document-text class="size-4 shrink-0" />
                            {{ __('Descrição') }}
                        </div>
                        <flux:text class="mt-2 whitespace-pre-wrap text-slate-800 dark:text-slate-100">{{ $actionIncident->description }}</flux:text>
                    </flux:callout>
                @endif

                @if ($actionIncident->call_requests_count > 0)
                    <flux:callout class="border-s-amber-300 bg-amber-50/80 dark:border-amber-700/60 dark:bg-amber-950/30">
                        <div class="flex items-center gap-2 text-xs font-semibold uppercase tracking-wide text-amber-900 dark:text-amber-200">
                            <flux:icon.phone-arrow-down-left class="size-4 shrink-0" />
                            {{ trans_choice('{1} :count solicitação para este ponto|[2,*] :count solicitações para este ponto', $actionIncident->totalCallRequestCount()) }}
                        </div>
                        <ul class="mt-2 space-y-1.5">
                            @foreach ($actionIncident->callRequests as $callRequest)
                                <li wire:key="call-request-{{ $callRequest->id }}" class="text-sm text-slate-800 dark:text-slate-100">
                                    <span class="font-semibold tabular-nums">{{ $callRequest->created_at?->format('d/m/Y H:i') ?? '—' }}</span>
                                    · {{ collect([$callRequest->caller_name, $callRequest->caller_phone])->filter()->join(' · ') ?: __('Solicitante não informado') }}
                                    @if ($callRequest->creator?->name)
                                        <span class="text-xs text-slate-600 dark:text-slate-400">({{ $callRequest->creator->name }})</span>
                                    @endif
                                </li>
                            @endforeach
                        </ul>
                    </flux:callout>
                @endif

                <div class="grid gap-2 border-t border-slate-200/90 pt-4 dark:border-slate-800 sm:grid-cols-2">
                    @if ($canDispatch)
                        <flux:button
                            variant="primary"
                            color="emerald"
                            icon="truck"
                            wire:click="openDispatchFromDetail"
                            class="w-full"
                        >
                            {{ __('Empenhar viatura') }}
                        </flux:button>
                    @endif

                    <flux:button
                        variant="primary"
                        color="blue"
                        :href="route('operations.incidents.show', $actionIncident)"
                        icon="arrow-top-right-on-square"
                        wire:navigate
                        class="w-full"
                    >
                        {{ __('Abrir ocorrência completa') }}
                    </flux:button>

                    @if ($canObserve)
                        <flux:button
                            variant="primary"
                            color="amber"
                            icon="chat-bubble-left-ellipsis"
                            wire:click="openObservationFromDetail"
                            class="w-full"
                        >
                            {{ __('Adicionar à descrição') }}
                        </flux:button>
                    @endif

                    @if ($canCancel)
                        <flux:button
                            variant="danger"
                            icon="x-circle"
                            wire:click="openCancelFromDetail"
                            class="w-full"
                        >
                            {{ __('Cancelar ocorrência') }}
                        </flux:button>
                    @endif

                    <flux:button
                        variant="outline"
                        wire:click="closeActionModals"
                        class="{{ ($canDispatch || $canObserve || $canCancel) ? 'sm:col-span-2' : '' }} w-full"
                    >
                        {{ __('Fechar') }}
                    </flux:button>
                </div>
            </div>
        </div>
    @endif
</flux:modal>
