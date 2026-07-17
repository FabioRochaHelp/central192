{{-- Modal: ocorrência já registrada neste ponto (raio configurável) --}}
<flux:modal wire:model.self="showDuplicateModal" wire:close="dismissDuplicateModal" variant="floating" class="max-w-3xl">
    <div class="space-y-5">
        <div>
            <flux:heading size="lg">{{ __('Já existe ocorrência neste ponto') }}</flux:heading>
            <flux:text class="mt-1 text-zinc-600 dark:text-zinc-400">
                {{ trans_choice(
                    '{1} Uma ocorrência ativa foi encontrada a até :raio m deste endereço. Some esta ligação como nova solicitação do mesmo ponto ou siga com o cadastro de uma ocorrência separada.|[2,*] :count ocorrências ativas foram encontradas a até :raio m deste endereço. Some esta ligação como nova solicitação do mesmo ponto ou siga com o cadastro de uma ocorrência separada.',
                    $duplicateCandidates->count(),
                    ['raio' => (int) config('operations.duplicate_incident_radius_meters')],
                ) }}
            </flux:text>
        </div>

        @error('duplicate')
            <flux:callout variant="danger" class="text-sm">{{ $message }}</flux:callout>
        @enderror

        <div class="space-y-3">
            @foreach ($duplicateCandidates as $candidate)
                @php
                    $candidateCallType = \App\Domain\Operations\Enums\CallType::tryFrom((string) $candidate->patient_call_type);
                    $candidateAccent = $candidateCallType?->dispatchQueueAccentClasses()
                        ?? \App\Domain\Operations\Enums\CallType::Normal->dispatchQueueAccentClasses();
                    $activeDispatches = $candidate->dispatches;
                @endphp

                <div wire:key="duplicate-{{ $candidate->id }}" class="cco-table-shell overflow-hidden rounded-xl border border-slate-200/95 dark:border-slate-700/80">
                    <div class="flex flex-wrap items-start justify-between gap-3 border-b border-slate-200/90 px-4 py-3 dark:border-slate-700/80 {{ $candidateAccent['row'] }}">
                        <div class="flex min-w-0 items-start gap-3">
                            <span
                                class="inline-flex h-9 min-w-9 shrink-0 items-center justify-center rounded-lg border px-2 font-mono text-sm font-bold {{ $candidateAccent['badge'] }}"
                                title="{{ $candidateCallType?->label() ?? __('Sem classificação') }}"
                            >
                                {{ $candidate->patient_call_type ?: '—' }}
                            </span>
                            <div class="min-w-0">
                                <div class="flex flex-wrap items-center gap-2">
                                    <flux:heading size="sm" class="tabular-nums">
                                        {{ __('Talão :talao/:ano', ['talao' => $candidate->talao, 'ano' => $candidate->dispatch_year]) }}
                                    </flux:heading>
                                    <x-incident.status-badge :status="$candidate->status" />
                                    @if ($candidate->distance_meters !== null)
                                        <span class="inline-flex items-center rounded-full border border-slate-300 bg-white/70 px-2 py-0.5 text-xs font-semibold tabular-nums text-slate-700 dark:border-slate-600 dark:bg-slate-900/60 dark:text-slate-200">
                                            {{ __(':m m daqui', ['m' => $candidate->distance_meters]) }}
                                        </span>
                                    @endif
                                    @if ($candidate->call_requests_count > 0)
                                        <span class="inline-flex items-center rounded-full border border-amber-400 bg-amber-100 px-2 py-0.5 text-xs font-semibold tabular-nums text-amber-900 dark:border-amber-600/70 dark:bg-amber-950/50 dark:text-amber-200">
                                            {{ trans_choice('{1} :count solicitação|[2,*] :count solicitações', $candidate->totalCallRequestCount()) }}
                                        </span>
                                    @endif
                                </div>
                                <flux:text class="mt-1 text-sm text-slate-700 dark:text-slate-200">
                                    {{ $candidate->occurred_at?->format('d/m/Y H:i') ?? '—' }}
                                    @if ($candidate->nature?->name)
                                        · {{ $candidate->nature->name }}
                                    @endif
                                </flux:text>
                            </div>
                        </div>
                        <div class="flex flex-wrap gap-2">
                            <flux:button size="sm" variant="ghost" icon="eye" :href="route('operations.incidents.show', $candidate)" target="_blank">
                                {{ __('Ver ocorrência') }}
                            </flux:button>
                            @if ($candidate->latitude !== null && $candidate->longitude !== null)
                                <flux:button size="sm" variant="ghost" icon="map" :href="route('operations.incidents.route-map', $candidate)" target="_blank">
                                    {{ __('Mapa') }}
                                </flux:button>
                            @endif
                        </div>
                    </div>

                    <div class="space-y-3 px-4 py-3">
                        <div class="grid gap-3 sm:grid-cols-2">
                            <div>
                                <div class="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">{{ __('Endereço') }}</div>
                                <flux:text class="mt-0.5 text-sm">
                                    {{ collect([$candidate->address_line, $candidate->number, $candidate->district, $candidate->city])->filter()->join(', ') ?: '—' }}
                                </flux:text>
                            </div>
                            <div>
                                <div class="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">{{ __('Solicitante') }}</div>
                                <flux:text class="mt-0.5 text-sm">
                                    {{ collect([$candidate->caller_name, $candidate->caller_phone])->filter()->join(' · ') ?: '—' }}
                                </flux:text>
                            </div>
                        </div>

                        @if ($activeDispatches->isNotEmpty())
                            <div class="rounded-lg border border-blue-200 bg-blue-50/70 px-3 py-2 dark:border-blue-900/60 dark:bg-blue-950/30">
                                <div class="text-xs font-semibold uppercase tracking-wide text-blue-800 dark:text-blue-200">
                                    {{ __('Em atendimento') }}
                                </div>
                                <ul class="mt-1.5 space-y-1.5">
                                    @foreach ($activeDispatches as $dispatch)
                                        @php
                                            $vehicle = $dispatch->shift?->vehicle;
                                            $position = $vehicle?->position;
                                            $vehicleDistanceMeters = ($position && $candidate->latitude !== null && $candidate->longitude !== null)
                                                ? (int) round(\App\Support\Operations\GeoDistance::haversineMeters(
                                                    (float) $position->latitude,
                                                    (float) $position->longitude,
                                                    (float) $candidate->latitude,
                                                    (float) $candidate->longitude,
                                                ))
                                                : null;
                                        @endphp
                                        <li wire:key="duplicate-{{ $candidate->id }}-dispatch-{{ $dispatch->id }}" class="text-sm text-slate-800 dark:text-slate-100">
                                            <span class="font-semibold">{{ $vehicle?->prefix ?? $vehicle?->plate ?? __('Viatura') }}</span>
                                            <span class="text-slate-600 dark:text-slate-300">· {{ $dispatch->stage?->label() ?? '—' }}</span>
                                            @if ($position)
                                                <div class="text-xs text-slate-600 dark:text-slate-400">
                                                    {{ $position->address ?: __('Posição sem endereço') }}
                                                    @if ($vehicleDistanceMeters !== null)
                                                        · {{ __(':m m da ocorrência', ['m' => $vehicleDistanceMeters]) }}
                                                    @endif
                                                    · {{ __('atualizada :quando', ['quando' => $position->fix_time?->diffForHumans() ?? '—']) }}
                                                </div>
                                            @else
                                                <div class="text-xs text-slate-500 dark:text-slate-400">{{ __('Sem posição conhecida.') }}</div>
                                            @endif
                                        </li>
                                    @endforeach
                                </ul>
                            </div>
                        @endif

                        @if ($candidate->description)
                            <div>
                                <div class="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">{{ __('Descrição atual') }}</div>
                                <flux:text class="mt-1 max-h-32 overflow-y-auto whitespace-pre-wrap text-sm text-slate-800 dark:text-slate-100">
                                    {{ $candidate->description }}
                                </flux:text>
                            </div>
                        @endif

                        <flux:button
                            variant="primary"
                            size="sm"
                            icon="plus-circle"
                            wire:click="attachRequestToIncident({{ $candidate->id }})"
                            wire:loading.attr="disabled"
                            wire:target="attachRequestToIncident"
                        >
                            {{ __('Somar solicitação a este talão') }}
                        </flux:button>
                    </div>
                </div>
            @endforeach
        </div>

        <flux:textarea
            wire:model="duplicateNotes"
            :label="__('Descrição adicional')"
            :description="__('Anexada à descrição da ocorrência escolhida. O texto anterior é mantido, com data, hora e operador.')"
            rows="3"
            placeholder="{{ __('O que esta ligação acrescenta…') }}"
        />

        <div class="flex flex-wrap items-center gap-2 border-t border-slate-200/95 pt-4 dark:border-slate-700/60">
            <flux:button variant="outline" icon="arrow-right" wire:click="dismissDuplicateModal">
                {{ __('É outro evento — continuar o cadastro') }}
            </flux:button>
            <flux:text size="sm" class="text-zinc-500">
                {{ __('O cadastro segue normalmente; este aviso não se repete ao salvar.') }}
            </flux:text>
        </div>
    </div>
</flux:modal>
