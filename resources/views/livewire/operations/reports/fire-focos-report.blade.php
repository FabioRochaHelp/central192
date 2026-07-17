<div class="cco-page-gap">
    @assets
        <script src="https://cdn.jsdelivr.net/npm/chart.js@4/dist/chart.umd.min.js"></script>
    @endassets

    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div class="flex items-center gap-3">
            <span class="flex size-11 shrink-0 items-center justify-center rounded-xl bg-orange-50 text-orange-600 ring-1 ring-orange-100 dark:bg-orange-900/25 dark:text-orange-400 dark:ring-orange-900/40">
                <flux:icon.fire class="size-6" />
            </span>
            <div>
                <flux:heading size="xl" class="tracking-tight text-slate-800 dark:text-slate-100">{{ __('Relatório de focos de calor') }}</flux:heading>
                <flux:text class="mt-0.5 text-slate-600 dark:text-slate-400">{{ __('Focos de calor (INPE) agregados por período, satélite, bioma e município.') }}</flux:text>
            </div>
        </div>
        <div class="flex items-center gap-2">
            <flux:button variant="ghost" icon="table-cells" wire:click="exportCsv">{{ __('CSV') }}</flux:button>
            <flux:button variant="primary" color="orange" icon="map" :href="route('dashboard.fire-map')" wire:navigate>{{ __('Mapa') }}</flux:button>
        </div>
    </div>

    <flux:card>
        <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-5">
            <flux:input wire:model.live="from" type="date" :label="__('De')" />
            <flux:input wire:model.live="to" type="date" :label="__('Até')" />
            <flux:input wire:model.live="estado" :label="__('UF')" maxlength="2" placeholder="SP" />
            <flux:input wire:model.live="bioma" :label="__('Bioma')" placeholder="Cerrado" />
            <flux:input wire:model.live="satelite" :label="__('Satélite')" />
        </div>
    </flux:card>

    @error('connection')
        <flux:callout variant="danger">{{ $message }}</flux:callout>
    @enderror

    @if ($connectionError)
        <flux:callout variant="danger">{{ __('Não foi possível conectar ao banco de focos satelitais.') }}</flux:callout>
    @elseif ($data)
        <div class="grid gap-4 sm:grid-cols-3">
            <flux:card class="border-t-2 border-orange-500">
                <flux:text size="sm" class="text-zinc-500">{{ __('Total de focos') }}</flux:text>
                <div class="mt-1 text-3xl font-bold tabular-nums text-orange-600 dark:text-orange-400">{{ number_format($data['total'], 0, ',', '.') }}</div>
            </flux:card>
            <flux:card class="border-t-2 border-blue-500/70">
                <flux:text size="sm" class="text-zinc-500">{{ __('Satélites distintos') }}</flux:text>
                <div class="mt-1 text-3xl font-bold tabular-nums text-blue-800 dark:text-blue-300">{{ count($data['by_satelite']) }}</div>
            </flux:card>
            <flux:card class="border-t-2 border-blue-500/70">
                <flux:text size="sm" class="text-zinc-500">{{ __('Municípios distintos') }}</flux:text>
                <div class="mt-1 text-3xl font-bold tabular-nums text-blue-800 dark:text-blue-300">{{ count($data['by_municipio']) }}</div>
            </flux:card>
        </div>

        <flux:card class="space-y-3">
            <x-card-heading tone="orange">
                <x-slot:icon><flux:icon.chart-bar class="size-4" /></x-slot:icon>
                {{ __('Focos por dia') }}
            </x-card-heading>
            @if (empty($data['by_day']))
                <flux:text class="text-zinc-500">{{ __('Sem dados no período.') }}</flux:text>
            @else
                <div wire:key="focos-day-{{ $data['period']['from'] }}-{{ $data['period']['to'] }}-{{ $data['total'] }}" wire:ignore x-data="{
                    init() {
                        new Chart(this.$refs.canvas, {
                            type: 'line',
                            data: {
                                labels: {{ Js::from(array_map(fn ($r) => \Illuminate\Support\Carbon::parse($r['date'])->format('d/m'), $data['by_day'])) }},
                                datasets: [{ label: 'Focos', data: {{ Js::from(array_column($data['by_day'], 'count')) }}, borderColor: '#f6600f', backgroundColor: 'rgba(246,96,15,0.15)', fill: true, tension: 0.3 }]
                            },
                            options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true, ticks: { precision: 0 } } } }
                        });
                    }
                }">
                    <div class="h-64"><canvas x-ref="canvas"></canvas></div>
                </div>
            @endif
        </flux:card>

        <div class="grid gap-4 lg:grid-cols-3">
            <flux:card class="space-y-3">
                <x-card-heading tone="blue">
                    <x-slot:icon><flux:icon.signal class="size-4" /></x-slot:icon>
                    {{ __('Por satélite') }}
                </x-card-heading>
                @include('livewire.operations.reports.partials.focos-count-table', ['rows' => $data['by_satelite']])
            </flux:card>

            <flux:card class="space-y-3">
                <x-card-heading tone="blue">
                    <x-slot:icon><flux:icon.globe-alt class="size-4" /></x-slot:icon>
                    {{ __('Por bioma') }}
                </x-card-heading>
                @include('livewire.operations.reports.partials.focos-count-table', ['rows' => $data['by_bioma']])
            </flux:card>

            <flux:card class="space-y-3">
                <x-card-heading tone="blue">
                    <x-slot:icon><flux:icon.map-pin class="size-4" /></x-slot:icon>
                    {{ __('Por município (top 15)') }}
                </x-card-heading>
                @include('livewire.operations.reports.partials.focos-count-table', ['rows' => $data['by_municipio']])
            </flux:card>
        </div>
    @endif
</div>
