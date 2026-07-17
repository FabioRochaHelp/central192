@php
    $incidentUrlTemplate = url('/operations/incidents/__ID__');
    $nearestVehicleUrlTemplate = url('/operations/incidents/__ID__/nearest-vehicle');
@endphp

{{-- Marcadores via Reverb no dispatchMap; este wire:poll longo é fallback dos contadores/lista. --}}
<div wire:poll.60s>

    {{-- ── Mapa Leaflet — fixo, preenche viewport abaixo do header ────────── --}}
    <div
        wire:ignore
        x-data="dispatchMap({
            incidents:       {{ Js::from($mapIncidents) }},
            alertClusters: {{ Js::from($mapAlertClusters) }},
            vehicles:        {{ Js::from($mapVehicles) }},
            incidentUrlBase: '{{ $incidentUrlTemplate }}',
            vehiclesUrl:     '{{ route('operations.map.vehicles') }}',
            municipiosUrl:   '{{ route('operations.cadastro.bases.geojson') }}',
            windUrl:         '{{ route('operations.map.wind') }}',
            nearestVehicleUrlBase: '{{ $nearestVehicleUrlTemplate }}'
        })"
        style="position:fixed; top:3rem; left:0; right:0; bottom:0; z-index:0"
    >
        <div x-ref="dispatchMapEl" style="width:100%; height:100%"></div>

        {{-- ── Painel lateral da ocorrência selecionada (Alpine, fora do ciclo Livewire) ── --}}
        <div
            x-show="panel.open"
            x-cloak
            x-transition.opacity
            class="w-72 overflow-hidden rounded-xl border border-slate-200/80 bg-white/95 shadow-2xl backdrop-blur-sm dark:border-slate-700/60 dark:bg-slate-900/95"
            style="position:fixed; top:calc(3rem + 0.75rem); right:0.75rem; z-index:20"
        >
            <div class="flex items-start justify-between gap-2 border-b border-slate-100 px-4 py-3 dark:border-slate-700/60">
                <div class="min-w-0">
                    <p class="truncate text-sm font-semibold text-slate-800 dark:text-slate-100" x-text="panel.title"></p>
                    <p class="text-[11px] tabular-nums text-slate-400" x-text="'Talão ' + panel.talao"></p>
                </div>
                <button
                    type="button"
                    @click="closePanel()"
                    class="shrink-0 rounded-md p-1 text-slate-400 hover:bg-slate-100 hover:text-slate-600 dark:hover:bg-slate-800"
                    aria-label="{{ __('Fechar') }}"
                >
                    <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor"><path d="M6.28 5.22a.75.75 0 0 0-1.06 1.06L8.94 10l-3.72 3.72a.75.75 0 1 0 1.06 1.06L10 11.06l3.72 3.72a.75.75 0 1 0 1.06-1.06L11.06 10l3.72-3.72a.75.75 0 0 0-1.06-1.06L10 8.94 6.28 5.22Z"/></svg>
                </button>
            </div>

            <div class="space-y-3 px-4 py-3">
                <div class="flex items-center gap-2">
                    <span
                        class="inline-block h-2.5 w-2.5 shrink-0 rounded-full"
                        :class="panel.statusOpen ? 'bg-red-500' : 'bg-blue-600'"
                    ></span>
                    <span class="text-xs font-medium text-slate-600 dark:text-slate-300" x-text="panel.statusLabel"></span>
                </div>

                <div>
                    <p class="text-[10px] font-semibold uppercase tracking-widest text-slate-400">{{ __('Coordenadas') }}</p>
                    <p class="text-xs tabular-nums text-slate-600 dark:text-slate-300"
                       x-text="(panel.lat?.toFixed(5) ?? '—') + ', ' + (panel.lng?.toFixed(5) ?? '—')"></p>
                </div>

                <div class="rounded-lg border border-slate-100 bg-slate-50/70 p-3 dark:border-slate-700/60 dark:bg-slate-800/40">
                    <p class="text-[10px] font-semibold uppercase tracking-widest text-slate-400">{{ __('Viatura mais próxima') }}</p>

                    <template x-if="panel.loading">
                        <p class="mt-1 text-xs text-slate-400">{{ __('Calculando rota…') }}</p>
                    </template>

                    <template x-if="!panel.loading && panel.error">
                        <p class="mt-1 text-xs text-amber-600 dark:text-amber-400" x-text="panel.error"></p>
                    </template>

                    <template x-if="!panel.loading && !panel.error && panel.vehiclePrefix">
                        <div class="mt-1 space-y-2">
                            <p class="text-sm font-semibold text-green-600 dark:text-green-400" x-text="panel.vehiclePrefix"></p>
                            <dl class="space-y-1 text-xs">
                                <div class="flex items-center justify-between">
                                    <dt class="text-slate-400">{{ __('Distância') }}</dt>
                                    <dd class="font-medium tabular-nums text-slate-700 dark:text-slate-200" x-text="panel.distance"></dd>
                                </div>
                                <div class="flex items-center justify-between">
                                    <dt class="text-slate-400">{{ __('Tempo') }}</dt>
                                    <dd class="font-medium tabular-nums text-slate-700 dark:text-slate-200" x-text="panel.duration"></dd>
                                </div>
                                <div class="flex items-center justify-between">
                                    <dt class="text-slate-400">{{ __('Chegada prevista') }}</dt>
                                    <dd class="font-semibold tabular-nums text-slate-800 dark:text-slate-100" x-text="panel.eta"></dd>
                                </div>
                            </dl>
                        </div>
                    </template>
                </div>

                <a
                    :href="panel.url"
                    target="_blank"
                    class="block rounded-lg bg-blue-600 px-3 py-2 text-center text-xs font-semibold text-white hover:bg-blue-700"
                >{{ __('Ver ocorrência') }}</a>
            </div>
        </div>
    </div>

    {{-- ── Painel situação + legenda — fixo, canto superior esquerdo ─────── --}}
    <div
        class="w-52 rounded-xl border border-slate-200/80 bg-white/90 shadow-lg backdrop-blur-sm dark:border-slate-700/60 dark:bg-slate-900/90"
        style="position:fixed; top:calc(3rem + 0.75rem); left:0.75rem; z-index:10"
    >
        <div class="space-y-1.5 p-3">
            <p class="text-[10px] font-semibold uppercase tracking-widest text-slate-400 dark:text-slate-500">{{ __('Situação') }}</p>

            <div class="flex items-center gap-2">
                <span class="inline-block h-2.5 w-2.5 shrink-0 rounded-full bg-red-500"></span>
                <span class="text-xs text-slate-700 dark:text-slate-200">
                    <strong>{{ $mapIncidents->where('status', 'open')->count() }}</strong>
                    {{ __('abertas') }}
                </span>
            </div>
            <div class="flex items-center gap-2">
                <span class="inline-block h-2.5 w-2.5 shrink-0 rounded-full bg-blue-600"></span>
                <span class="text-xs text-slate-700 dark:text-slate-200">
                    <strong>{{ $mapIncidents->where('status', '!=', 'open')->count() }}</strong>
                    {{ __('despachada(s)') }}
                </span>
            </div>
            <div class="flex items-center gap-2">
                <span class="inline-block h-2.5 w-2.5 shrink-0 rounded-full bg-green-500"></span>
                <span class="text-xs text-slate-700 dark:text-slate-200">
                    <strong>{{ $mapVehicles->where('on_dispatch', true)->count() }}</strong>
                    {{ __('em atendimento') }}
                </span>
            </div>
            <div class="flex items-center gap-2">
                <span class="inline-block h-2.5 w-2.5 shrink-0 rounded-full bg-slate-400"></span>
                <span class="text-xs text-slate-700 dark:text-slate-200">
                    <strong>{{ $mapVehicles->where('on_dispatch', false)->count() }}</strong>
                    {{ __('disponível(is) na base') }}
                </span>
            </div>

            @if ($mapIncidents->isEmpty() && $mapVehicles->isEmpty())
                <p class="text-xs text-slate-400">{{ __('Sem coordenadas.') }}</p>
            @endif
        </div>

        <div class="mx-3 border-t border-slate-100 dark:border-slate-700/60"></div>

        <div class="space-y-1 p-3">
            <p class="text-[10px] font-semibold uppercase tracking-widest text-slate-400 dark:text-slate-500">{{ __('Legenda') }}</p>
            <div class="flex items-center gap-2 text-[11px] text-slate-600 dark:text-slate-300">
                <span class="inline-block h-2 w-2 shrink-0 rounded-full bg-red-500"></span>{{ __('Ocorrência aberta') }}
            </div>
            <div class="flex items-center gap-2 text-[11px] text-slate-600 dark:text-slate-300">
                <span class="inline-block h-2 w-2 shrink-0 rounded-full bg-blue-600"></span>{{ __('Despachada') }}
            </div>
            <div class="flex items-center gap-2 text-[11px] text-slate-600 dark:text-slate-300">
                <span class="inline-block h-2 w-2 shrink-0 rounded-full bg-green-500"></span>{{ __('Viatura em atendimento') }}
            </div>
            <div class="flex items-center gap-2 text-[11px] text-slate-600 dark:text-slate-300">
                <span class="inline-block h-2 w-2 shrink-0 rounded-full bg-slate-400"></span>{{ __('Viatura disponível na base') }}
            </div>
            <div class="flex items-center gap-2 text-[11px] text-slate-600 dark:text-slate-300">
                <span class="inline-block h-2 w-2 shrink-0 rounded-full bg-amber-500 ring-2 ring-amber-300"></span>{{ __('Chamada (alerta)') }}
            </div>
            <div class="flex items-center gap-2 text-[11px] text-slate-600 dark:text-slate-300">
                <span class="inline-block h-2 w-2 shrink-0 rounded-full bg-violet-500 ring-2 ring-violet-300"></span>{{ __('Múltiplas chamadas') }}
            </div>
            <div class="flex items-center gap-2 text-[11px] text-slate-600 dark:text-slate-300">
                <span class="inline-block h-2.5 w-3.5 shrink-0 rounded-sm border border-blue-500 bg-blue-500/20"></span>{{ __('Base ativa') }}
            </div>
            <div class="flex items-center gap-2 text-[11px] text-slate-600 dark:text-slate-300">
                <span class="inline-block h-2.5 w-3.5 shrink-0 rounded-sm border border-slate-400 bg-slate-300/20"></span>{{ __('Base inativa') }}
            </div>
        </div>

        <div class="mx-3 border-t border-slate-100 dark:border-slate-700/60"></div>
        <p class="px-3 py-2 text-[10px] text-slate-400 dark:text-slate-500">{{ __('Atualiza a cada 15s') }}</p>
    </div>

    {{-- ── Lista de ocorrências — fixo, canto inferior direito ────────────── --}}
    @if ($mapIncidents->isNotEmpty())
        <div
            class="w-64 rounded-xl border border-slate-200/80 bg-white/90 shadow-lg backdrop-blur-sm dark:border-slate-700/60 dark:bg-slate-900/90"
            style="position:fixed; bottom:1rem; right:0.75rem; z-index:10"
        >
            <div class="border-b border-slate-100 px-3 py-2 dark:border-slate-700/60">
                <p class="text-[10px] font-semibold uppercase tracking-widest text-slate-400 dark:text-slate-500">
                    {{ __('Ocorrências no mapa') }} ({{ $mapIncidents->count() }})
                </p>
            </div>
            <ul class="max-h-56 divide-y divide-slate-100 overflow-y-auto dark:divide-slate-700/50">
                @foreach ($mapIncidents as $inc)
                    <li>
                        <a
                            href="{{ $inc['url'] }}"
                            target="_blank"
                            class="flex items-center gap-2 px-3 py-2 text-xs hover:bg-slate-50 dark:hover:bg-slate-800/60"
                        >
                            <span class="inline-block h-2 w-2 shrink-0 rounded-full {{ $inc['status'] === 'open' ? 'bg-red-500' : 'bg-blue-600' }}"></span>
                            <span class="min-w-0 flex-1 truncate font-medium text-slate-700 dark:text-slate-200">{{ $inc['nature'] }}</span>
                            <span class="shrink-0 tabular-nums text-slate-400">{{ $inc['talao'] }}/{{ $inc['year'] }}</span>
                        </a>
                    </li>
                @endforeach
            </ul>
        </div>
    @endif

</div>
