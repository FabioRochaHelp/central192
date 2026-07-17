<div class="flex flex-col gap-8">
    @can('viewAny', \App\Models\Incident::class)
        <div class="mb-0">
            <flux:heading size="xl" class="tracking-tight text-slate-800 dark:text-slate-100">{{ __('Painel') }}</flux:heading>
            <flux:text class="mt-1 max-w-2xl text-slate-600 dark:text-slate-400">{{ __('Acesso rápido ao núcleo operacional — use os atalhos abaixo ou o menu lateral.') }}</flux:text>
        </div>

        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
            <a href="{{ route('operations.dispatch') }}" wire:navigate class="cco-quick-link">
                <flux:icon.radio class="size-5 text-blue-700 dark:text-blue-400/90" />
                <span class="cco-quick-link-title">{{ __('Centro de Controle de Operações') }}</span>
                <span class="cco-quick-link-meta">{{ __('CCO · despacho') }}</span>
            </a>
            <a href="{{ route('operations.incidents.index') }}" wire:navigate class="cco-quick-link">
                <flux:icon.rectangle-stack class="size-5 text-blue-700 dark:text-blue-400/90" />
                <span class="cco-quick-link-title">{{ __('Ocorrências') }}</span>
                <span class="cco-quick-link-meta">{{ __('Lista e acompanhamento') }}</span>
            </a>
            @if (auth()->user()?->hasOperationalAbility('incident.create'))
                <a href="{{ route('operations.incidents.start') }}" wire:navigate class="cco-quick-link">
                    <flux:icon.plus-circle class="size-5 text-blue-700 dark:text-blue-400/90" />
                    <span class="cco-quick-link-title">{{ __('Nova ocorrência') }}</span>
                    <span class="cco-quick-link-meta">{{ __('Informar telefone da chamada') }}</span>
                </a>
            @endif
            <a href="{{ route('operations.fleet') }}" wire:navigate class="cco-quick-link">
                <flux:icon.truck class="size-5 text-blue-700 dark:text-blue-400/90" />
                <span class="cco-quick-link-title">{{ __('Turnos e viaturas') }}</span>
                <span class="cco-quick-link-meta">{{ __('Escalas e disponibilidade') }}</span>
            </a>
            @if (auth()->user()?->isOperationalCentral())
                <a href="{{ route('operations.parameters.natures') }}" wire:navigate class="cco-quick-link">
                    <flux:icon.adjustments-horizontal class="size-5 text-blue-700 dark:text-blue-400/90" />
                    <span class="cco-quick-link-title">{{ __('Parâmetros da ocorrência') }}</span>
                    <span class="cco-quick-link-meta">{{ __('Cadastros globais') }}</span>
                </a>
            @endif
            @if (auth()->user()?->isOperationalCentral())
                <a href="{{ route('operations.cadastro.bases') }}" wire:navigate class="cco-quick-link">
                    <flux:icon.building-office-2 class="size-5 text-blue-700 dark:text-blue-400/90" />
                    <span class="cco-quick-link-title">{{ __('Bases') }}</span>
                    <span class="cco-quick-link-meta">{{ __('Municípios contratantes') }}</span>
                </a>
            @endif
            <a href="{{ route('operations.cadastro.vehicles') }}" wire:navigate class="cco-quick-link">
                <flux:icon.cube class="size-5 text-blue-700 dark:text-blue-400/90" />
                <span class="cco-quick-link-title">{{ __('Viaturas') }}</span>
                <span class="cco-quick-link-meta">{{ __('Unidades móveis') }}</span>
            </a>
            <a href="{{ route('operations.cadastro.staff') }}" wire:navigate class="cco-quick-link">
                <flux:icon.users class="size-5 text-blue-700 dark:text-blue-400/90" />
                <span class="cco-quick-link-title">{{ __('Efetivo') }}</span>
                <span class="cco-quick-link-meta">{{ __('Equipe operacional') }}</span>
            </a>
        </div>
    @endcan

    @if ($showCallStats)
        <div wire:key="call-stats-{{ $callStatsBroadcastTick }}">
            <flux:card class="space-y-4">
                <div>
                    <flux:heading size="lg">{{ __('Chamadas por tipo') }}</flux:heading>
                    <flux:text class="mt-1 text-sm text-zinc-600 dark:text-zinc-400">
                        {{ __('Totais de hoje no seu escopo operacional (fuso :timezone).', ['timezone' => config('app.timezone')]) }}
                    </flux:text>
                    <flux:text size="sm" class="mt-1 text-zinc-500 dark:text-zinc-400">
                        {{ __('Atualização em tempo real via WebSocket (Reverb), ao criar ou alterar ocorrências relevantes.') }}
                    </flux:text>
                </div>
                <div class="grid gap-3 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-6">
                    @foreach ($callTypeStats as $row)
                        @php
                            [$cardCls, $dotCls, $labelCls, $countCls] = match ($row['code']) {
                                'C' => [
                                    'border-amber-200  bg-amber-50/80  dark:border-amber-800  dark:bg-amber-900/20',
                                    'bg-amber-500',
                                    'text-amber-700  dark:text-amber-300',
                                    'text-amber-600  dark:text-amber-400',
                                ],
                                'T' => [
                                    'border-orange-200 bg-orange-50/80 dark:border-orange-800 dark:bg-orange-900/20',
                                    'bg-orange-500',
                                    'text-orange-700 dark:text-orange-300',
                                    'text-orange-600 dark:text-orange-400',
                                ],
                                'A' => [
                                    'border-sky-200    bg-sky-50/80    dark:border-sky-800    dark:bg-sky-900/20',
                                    'bg-sky-500',
                                    'text-sky-700    dark:text-sky-300',
                                    'text-sky-600    dark:text-sky-400',
                                ],
                                'L' => [
                                    'border-violet-200 bg-violet-50/80 dark:border-violet-800 dark:bg-violet-900/20',
                                    'bg-violet-500',
                                    'text-violet-700 dark:text-violet-300',
                                    'text-violet-600 dark:text-violet-400',
                                ],
                                'N' => [
                                    'border-emerald-200 bg-emerald-50/80 dark:border-emerald-800 dark:bg-emerald-900/20',
                                    'bg-emerald-500',
                                    'text-emerald-700 dark:text-emerald-300',
                                    'text-emerald-600 dark:text-emerald-400',
                                ],
                                'U' => [
                                    'border-red-200    bg-red-50/80    dark:border-red-800    dark:bg-red-900/20',
                                    'bg-red-500',
                                    'text-red-700    dark:text-red-300',
                                    'text-red-600    dark:text-red-400',
                                ],
                                default => [
                                    'border-zinc-200   bg-zinc-50/80   dark:border-zinc-700   dark:bg-zinc-900/50',
                                    'bg-zinc-400',
                                    'text-zinc-700   dark:text-zinc-300',
                                    'text-zinc-600   dark:text-zinc-400',
                                ],
                            };
                        @endphp
                        <div wire:key="call-stat-{{ $row['code'] }}"
                             class="flex flex-col gap-3 rounded-xl border px-4 py-4 {{ $cardCls }}">
                            <div class="flex items-center gap-2">
                                <span class="h-2.5 w-2.5 shrink-0 rounded-full {{ $dotCls }}"></span>
                                <span class="text-sm font-semibold leading-tight {{ $labelCls }}">{{ $row['label'] }}</span>
                            </div>
                            <span class="text-4xl font-bold tabular-nums leading-none {{ $countCls }}"
                                  data-test="call-count-{{ $row['code'] }}">{{ $row['count'] }}</span>
                        </div>
                    @endforeach
                </div>
            </flux:card>
        </div>
    @if ($modalityStats !== null)
        @assets
            <script src="https://cdn.jsdelivr.net/npm/chart.js@4/dist/chart.umd.min.js"></script>
        @endassets

        @php
            $slices     = $modalityStats['slices'];
            $totalCount = $modalityStats['total'];
        @endphp

        <flux:card class="space-y-4">
            <div>
                <flux:heading size="lg">{{ __('Ocorrências por modalidade') }}</flux:heading>
                <flux:text class="mt-1 text-sm text-zinc-600 dark:text-zinc-400">
                    {{ $modalityStats['month'] }} · {{ $totalCount }} {{ __('ocorrência(s) no seu escopo') }}
                </flux:text>
            </div>

            @if ($totalCount === 0)
                <flux:text class="text-zinc-500">{{ __('Nenhuma ocorrência registrada neste mês.') }}</flux:text>
            @else
                <div class="flex flex-col items-center gap-6 sm:flex-row sm:items-start">
                    {{-- Donut Chart.js --}}
                    <div
                        class="relative shrink-0"
                        x-data="{
                            init() {
                                new Chart(this.$refs.canvas, {
                                    type: 'doughnut',
                                    data: {
                                        labels: {{ Js::from(array_column($slices, 'label')) }},
                                        datasets: [{
                                            data: {{ Js::from(array_column($slices, 'count')) }},
                                            backgroundColor: {{ Js::from(array_column($slices, 'color')) }},
                                            borderWidth: 2,
                                            borderColor: document.documentElement.classList.contains('dark') ? '#18181b' : '#ffffff',
                                            hoverBorderColor: 'transparent',
                                        }]
                                    },
                                    options: {
                                        responsive: false,
                                        cutout: '60%',
                                        plugins: {
                                            legend: { display: false },
                                            tooltip: {
                                                callbacks: {
                                                    label: (ctx) => ` ${ctx.parsed} ocorrência(s) (${ctx.dataset.data.reduce((a,b)=>a+b,0) > 0 ? Math.round(ctx.parsed / ctx.dataset.data.reduce((a,b)=>a+b,0) * 100) : 0}%)`
                                                }
                                            }
                                        }
                                    }
                                });
                            }
                        }"
                    >
                        <canvas x-ref="canvas" width="176" height="176"></canvas>
                        <div class="pointer-events-none absolute inset-0 flex flex-col items-center justify-center">
                            <span class="text-xl font-bold tabular-nums text-zinc-900 dark:text-zinc-50">{{ $totalCount }}</span>
                            <span class="text-xs text-zinc-500">{{ __('total') }}</span>
                        </div>
                    </div>

                    {{-- Legenda --}}
                    <ul class="flex flex-1 flex-col justify-center gap-2">
                        @foreach ($slices as $slice)
                            <li class="flex items-center justify-between gap-3 text-sm">
                                <div class="flex min-w-0 items-center gap-2">
                                    <span class="size-3 shrink-0 rounded-sm" style="background-color:{{ $slice['color'] }}"></span>
                                    <span class="truncate text-zinc-700 dark:text-zinc-300">{{ $slice['label'] }}</span>
                                </div>
                                <div class="flex shrink-0 items-center gap-2 tabular-nums">
                                    <span class="font-semibold text-zinc-900 dark:text-zinc-50">{{ $slice['count'] }}</span>
                                    <span class="w-10 text-end text-zinc-400">{{ $slice['percentage'] }}%</span>
                                </div>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif
        </flux:card>
    @endif

        {{-- ── Mapa de cobertura territorial ─────────────────────────────── --}}
        <flux:card class="space-y-4">
            <div class="flex items-start justify-between gap-4">
                <div>
                    <flux:heading size="lg">{{ __('Cobertura territorial') }}</flux:heading>
                    <flux:text class="mt-1 text-sm text-zinc-600 dark:text-zinc-400">
                        {{ __('Polígonos IBGE dos municípios operacionais cadastrados no sistema.') }}
                    </flux:text>
                </div>
                <span
                    x-data="{ c: 0 }"
                    @municipios-map-loaded.window="c = $event.detail.count"
                    x-show="c > 0"
                    class="shrink-0 inline-flex items-center rounded-full bg-blue-50 px-2.5 py-0.5 text-xs font-semibold text-blue-700 ring-1 ring-blue-200 dark:bg-blue-900/20 dark:text-blue-300 dark:ring-blue-800"
                    x-text="c + (c === 1 ? ' município' : ' municípios')"
                ></span>
            </div>

            <div
                wire:ignore
                x-data="municipiosOverviewMap({
                    featuresUrl: '{{ route('operations.cadastro.bases.geojson') }}'
                })"
                x-init="$watch('count', v => v > 0 && $dispatch('municipios-map-loaded', { count: v }))"
            >
                <div class="relative">

                    {{-- Loading overlay --}}
                    <div
                        x-show="loading"
                        class="absolute inset-0 z-10 flex flex-col items-center justify-center gap-2 rounded-xl bg-white/80 dark:bg-zinc-900/80"
                    >
                        <svg class="h-6 w-6 animate-spin text-blue-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path>
                        </svg>
                        <span class="text-xs text-zinc-500 dark:text-zinc-400">{{ __('Carregando polígonos…') }}</span>
                    </div>

                    {{-- Empty state --}}
                    <div
                        x-show="!loading && empty"
                        class="absolute inset-0 z-10 flex flex-col items-center justify-center gap-2 rounded-xl bg-zinc-50 dark:bg-zinc-800"
                    >
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8 text-zinc-300 dark:text-zinc-600" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 6.75V15m6-6v8.25m.503-10.498l4.875 2.437c.381.19.622.58.622 1.006v4.513c0 .754-.807 1.228-1.47.85l-4.63-2.565a1 1 0 00-.96 0l-4.63 2.565c-.662.378-1.47-.095-1.47-.85V8.695c0-.426.241-.816.622-1.006l4.875-2.437a1.125 1.125 0 011.07 0z" />
                        </svg>
                        <p class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('Nenhum município com geometria cadastrada.') }}</p>
                        <p class="text-xs text-zinc-400 dark:text-zinc-500">{{ __('Execute') }} <code class="rounded bg-zinc-200 px-1 dark:bg-zinc-700">php artisan municipios:vincular-ibge</code> {{ __('e importe os dados do IBGE.') }}</p>
                    </div>

                    {{-- Error state --}}
                    <div
                        x-show="!loading && error"
                        class="absolute inset-0 z-10 flex items-center justify-center rounded-xl bg-zinc-50 dark:bg-zinc-800"
                    >
                        <p class="text-sm text-red-500">{{ __('Erro ao carregar os dados geográficos.') }}</p>
                    </div>

                    {{-- Mapa --}}
                    <div
                        x-ref="mapEl"
                        class="w-full rounded-xl border border-zinc-200 bg-zinc-100 dark:border-zinc-700 dark:bg-zinc-800"
                        style="height:480px;z-index:0"
                    ></div>

                    {{-- Legenda --}}
                    <div class="pointer-events-none absolute bottom-3 left-3 z-10 rounded-lg border border-zinc-200/80 bg-white/90 px-3 py-2 text-[11px] shadow-sm backdrop-blur-sm dark:border-zinc-700/60 dark:bg-zinc-900/90">
                        <p class="mb-1 font-semibold uppercase tracking-wider text-zinc-400 dark:text-zinc-500" style="font-size:9px">{{ __('Legenda') }}</p>
                        <div class="flex items-center gap-1.5 text-zinc-600 dark:text-zinc-300">
                            <span class="inline-block h-3 w-4 rounded-sm border border-blue-500 bg-blue-500/20"></span>
                            {{ __('Base ativa') }}
                        </div>
                        <div class="mt-1 flex items-center gap-1.5 text-zinc-500 dark:text-zinc-400">
                            <span class="inline-block h-3 w-4 rounded-sm border border-slate-400 bg-slate-300/20"></span>
                            {{ __('Base inativa') }}
                        </div>
                    </div>
                </div>
            </div>
        </flux:card>

        {{-- ── Focos de incêndio ───────────────────────────────────────────── --}}
        <flux:card class="space-y-4" data-test="dashboard-fire-map">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <flux:heading size="lg">{{ __('Focos de incêndio') }}</flux:heading>
                    <flux:text class="mt-1 text-sm text-zinc-600 dark:text-zinc-400">
                        {{ __('Informe o período, opcionalmente o satélite, e carregue o mapa (OpenStreetMap).') }}
                    </flux:text>
                </div>
                <flux:button
                    :href="$this->fireMapFullscreenUrl()"
                    color="orange"
                    icon="arrow-top-right-on-square"
                    size="sm"
                    variant="primary"
                    target="_blank"
                    data-test="fire-map-fullscreen-link"
                >
                    {{ __('Abrir mapa em nova guia') }}
                </flux:button>
            </div>

            <div class="flex flex-wrap items-end gap-3">
                <flux:input
                    wire:model="fireMapFrom"
                    type="date"
                    :label="__('De')"
                    class="w-full sm:w-auto"
                />
                <flux:input
                    wire:model="fireMapTo"
                    type="date"
                    :label="__('Até')"
                    class="w-full sm:w-auto"
                />
                <flux:select
                    wire:model="fireMapSatelite"
                    :label="__('Satélite')"
                    class="w-full sm:w-auto"
                >
                    <flux:select.option value="">{{ __('Todos') }}</flux:select.option>
                    @foreach ($fireSatellites as $sat)
                        <flux:select.option :value="$sat">{{ $sat }}</flux:select.option>
                    @endforeach
                </flux:select>
                <flux:button
                    wire:click="loadFireMap"
                    wire:loading.attr="disabled"
                    wire:target="loadFireMap"
                    variant="primary"
                    icon="map-pin"
                >
                    <span wire:loading.remove wire:target="loadFireMap">{{ __('Carregar mapa') }}</span>
                    <span wire:loading wire:target="loadFireMap">{{ __('Carregando…') }}</span>
                </flux:button>
            </div>

            <flux:error name="fireMap" />

            <div
                wire:ignore
                x-data="dashboardFireMap({
                    focosUrl: '{{ route('dashboard.map.focos') }}',
                    countLabels: {
                        zero: @js(__('Nenhum foco no mapa')),
                        one: @js(__('1 foco no mapa')),
                        other: @js(__(':count focos no mapa')),
                        idle: @js(__('Informe o período e carregue o mapa')),
                    },
                })"
                @dashboard-fire-map-updated.window="setFocos($event.detail.focos ?? [])"
                class="relative space-y-3"
            >
                <span
                    x-ref="focoCount"
                    class="inline-block rounded-md border border-zinc-200 bg-zinc-50 px-2 py-0.5 text-xs text-zinc-500 dark:border-zinc-700 dark:bg-zinc-800"
                    x-text="countLabels.idle"
                ></span>

                <div class="relative">
                    <div
                        x-ref="fireMapEl"
                        class="min-h-[20rem] rounded-xl border border-zinc-200 bg-zinc-100 dark:border-zinc-700 dark:bg-zinc-800"
                        style="z-index:0"
                    ></div>

                    <div class="pointer-events-none absolute bottom-3 left-3 rounded-lg border border-zinc-200/80 bg-white/90 px-3 py-2 text-[11px] text-zinc-600 shadow-sm backdrop-blur-sm dark:border-zinc-700/60 dark:bg-slate-900/90 dark:text-zinc-300">
                        <div class="flex items-center gap-2">
                            <span class="inline-block h-2.5 w-2.5 shrink-0 rounded-full bg-orange-500"></span>
                            {{ __('Foco satelital') }}
                        </div>
                    </div>
                </div>
            </div>
        </flux:card>

    @else
        <div class="flex h-full w-full flex-1 flex-col gap-4 rounded-xl">
            <div class="grid auto-rows-min gap-4 md:grid-cols-3">
                <div class="relative aspect-video overflow-hidden rounded-xl border border-slate-200/95 bg-white/70 shadow-md shadow-slate-900/5 dark:border-slate-700/50 dark:bg-slate-900/40 dark:shadow-lg dark:shadow-black/30">
                    <x-placeholder-pattern class="absolute inset-0 size-full stroke-blue-700/15 dark:stroke-blue-400/15" />
                </div>
                <div class="relative aspect-video overflow-hidden rounded-xl border border-slate-200/95 bg-white/70 shadow-md shadow-slate-900/5 dark:border-slate-700/50 dark:bg-slate-900/40 dark:shadow-lg dark:shadow-black/30">
                    <x-placeholder-pattern class="absolute inset-0 size-full stroke-blue-700/15 dark:stroke-blue-400/15" />
                </div>
                <div class="relative aspect-video overflow-hidden rounded-xl border border-slate-200/95 bg-white/70 shadow-md shadow-slate-900/5 dark:border-slate-700/50 dark:bg-slate-900/40 dark:shadow-lg dark:shadow-black/30">
                    <x-placeholder-pattern class="absolute inset-0 size-full stroke-blue-700/15 dark:stroke-blue-400/15" />
                </div>
            </div>
            <div class="relative h-full min-h-[12rem] flex-1 overflow-hidden rounded-xl border border-slate-200/95 bg-white/60 shadow-inner shadow-slate-900/5 dark:border-slate-700/50 dark:bg-slate-900/35 dark:shadow-inner dark:shadow-black/40">
                <x-placeholder-pattern class="absolute inset-0 size-full stroke-blue-700/10 dark:stroke-blue-400/10" />
                <div class="relative z-10 flex h-full items-center justify-center p-6">
                    <flux:text class="max-w-md text-center text-sm text-slate-600 dark:text-slate-500">{{ __('Área reservada para indicadores, mapas ou filas — personalize conforme o CCO.') }}</flux:text>
                </div>
            </div>
        </div>
    @endif
</div>
