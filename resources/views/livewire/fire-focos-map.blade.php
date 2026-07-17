<div>
    {{-- Mapa Leaflet — fixo, preenche viewport abaixo do header --}}
    <div
        wire:ignore
        x-data="dashboardFireMap({
            focosUrl: '{{ route('dashboard.map.focos') }}',
            scarUrl: '{{ auth()->user()?->isOperationalCentral() ? route('operations.reports.fire-scars.index') : '' }}',
            countLabels: {
                zero: @js(__('Nenhum foco no mapa')),
                one: @js(__('1 foco no mapa')),
                other: @js(__(':count focos no mapa')),
                idle: @js(__('Carregando mapa…')),
            },
        })"
        x-init="$nextTick(() => setFocos(@js($mapFocos)))"
        @dashboard-fire-map-updated.window="setFocos($event.detail.focos ?? [])"
        style="position:fixed; top:3rem; left:0; right:0; bottom:0; z-index:0"
    >
        <div x-ref="fireMapEl" style="width:100%; height:100%"></div>
    </div>

    {{-- Painel de filtros — fixo, canto superior esquerdo --}}
    <div
        class="w-72 rounded-xl border border-slate-200/80 bg-white/90 shadow-lg backdrop-blur-sm dark:border-slate-700/60 dark:bg-slate-900/90"
        style="position:fixed; top:calc(3rem + 0.75rem); left:0.75rem; z-index:10"
        data-test="fire-map-fullscreen-panel"
    >
        <div class="space-y-3 p-3">
            <p class="text-[10px] font-semibold uppercase tracking-widest text-slate-400 dark:text-slate-500">{{ __('Filtros') }}</p>

            <flux:input
                wire:model="fireMapFrom"
                type="date"
                :label="__('De')"
            />
            <flux:input
                wire:model="fireMapTo"
                type="date"
                :label="__('Até')"
            />
            <flux:select
                wire:model="fireMapSatelite"
                :label="__('Satélite')"
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
                class="w-full"
            >
                <span wire:loading.remove wire:target="loadFireMap">{{ __('Carregar mapa') }}</span>
                <span wire:loading wire:target="loadFireMap">{{ __('Carregando…') }}</span>
            </flux:button>

            <flux:error name="fireMap" />
        </div>

        <div class="mx-3 border-t border-slate-100 dark:border-slate-700/60"></div>

        <div class="space-y-1 p-3">
            <p class="text-[10px] font-semibold uppercase tracking-widest text-slate-400 dark:text-slate-500">{{ __('Legenda') }}</p>
            <div class="flex items-center gap-2 text-[11px] text-slate-600 dark:text-slate-300">
                <span class="inline-block h-2.5 w-2.5 shrink-0 rounded-full bg-orange-500"></span>
                {{ __('Foco satelital') }}
            </div>
            <p class="pt-1 text-[10px] text-slate-400 dark:text-slate-500">
                {{ trans_choice(':count foco no mapa|:count focos no mapa', count($mapFocos), ['count' => count($mapFocos)]) }}
            </p>
        </div>
    </div>
</div>
