<div class="cco-page-gap">
    @assets
        <script src="https://cdn.jsdelivr.net/npm/chart.js@4/dist/chart.umd.min.js"></script>
    @endassets

    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div class="flex items-center gap-3">
            <span class="flex size-11 shrink-0 items-center justify-center rounded-xl bg-blue-50 text-blue-700 ring-1 ring-blue-100 dark:bg-blue-900/30 dark:text-blue-300 dark:ring-blue-900/40">
                <flux:icon.chart-bar class="size-6" />
            </span>
            <div>
                <flux:heading size="xl" class="tracking-tight text-slate-800 dark:text-slate-100">{{ __('Relatório de ocorrências') }}</flux:heading>
                <flux:text class="mt-0.5 text-slate-600 dark:text-slate-400">{{ __('Estatísticas consolidadas por período, município, natureza e modalidade.') }}</flux:text>
            </div>
        </div>
        <div class="flex items-center gap-2">
            <flux:button variant="ghost" icon="table-cells" wire:click="exportCsv">{{ __('CSV') }}</flux:button>
            <flux:button variant="primary" color="orange" icon="document-arrow-down" :href="route('operations.reports.incidents.document', ['from' => $from, 'to' => $to, 'municipio_id' => $municipioId, 'nature_id' => $natureId, 'modality' => $modality])" target="_blank">{{ __('PDF') }}</flux:button>
        </div>
    </div>

    <flux:card>
        <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-5">
            <flux:input wire:model.live="from" type="date" :label="__('De')" />
            <flux:input wire:model.live="to" type="date" :label="__('Até')" />
            <flux:select wire:model.live="municipioId" :label="__('Município')">
                <flux:select.option value="">{{ __('Todos') }}</flux:select.option>
                @foreach ($municipios as $m)
                    <flux:select.option :value="$m->id">{{ $m->city }}</flux:select.option>
                @endforeach
            </flux:select>
            <flux:select wire:model.live="natureId" :label="__('Natureza')">
                <flux:select.option value="">{{ __('Todas') }}</flux:select.option>
                @foreach ($natures as $n)
                    <flux:select.option :value="$n->id">{{ $n->name }}</flux:select.option>
                @endforeach
            </flux:select>
            <flux:select wire:model.live="modality" :label="__('Modalidade')">
                <flux:select.option value="">{{ __('Todas') }}</flux:select.option>
                @foreach ($modalities as $mod)
                    <flux:select.option :value="$mod->value">{{ $mod->label() }}</flux:select.option>
                @endforeach
            </flux:select>
        </div>
    </flux:card>

    {{-- KPIs --}}
    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <flux:card class="border-t-2 border-orange-500">
            <flux:text size="sm" class="text-zinc-500">{{ __('Total de ocorrências') }}</flux:text>
            <div class="mt-1 text-3xl font-bold tabular-nums text-orange-600 dark:text-orange-400">{{ number_format($data['total'], 0, ',', '.') }}</div>
        </flux:card>
        <flux:card class="border-t-2 border-blue-500/70">
            <flux:text size="sm" class="text-zinc-500">{{ __('Tempo médio empenho → local') }}</flux:text>
            <div class="mt-1 text-3xl font-bold tabular-nums text-blue-800 dark:text-blue-300">{{ $data['response_time']['avg_dispatch_to_scene_min'] !== null ? number_format($data['response_time']['avg_dispatch_to_scene_min'], 1, ',', '.').' min' : '—' }}</div>
        </flux:card>
        <flux:card class="border-t-2 border-blue-500/70">
            <flux:text size="sm" class="text-zinc-500">{{ __('Tempo médio chamada → local') }}</flux:text>
            <div class="mt-1 text-3xl font-bold tabular-nums text-blue-800 dark:text-blue-300">{{ $data['response_time']['avg_call_to_scene_min'] !== null ? number_format($data['response_time']['avg_call_to_scene_min'], 1, ',', '.').' min' : '—' }}</div>
        </flux:card>
        <flux:card class="border-t-2 border-blue-500/70">
            <flux:text size="sm" class="text-zinc-500">{{ __('Amostra (com tempos)') }}</flux:text>
            <div class="mt-1 text-3xl font-bold tabular-nums text-blue-800 dark:text-blue-300">{{ number_format($data['response_time']['sample'], 0, ',', '.') }}</div>
        </flux:card>
    </div>

    <div class="grid gap-4 lg:grid-cols-2">
        {{-- Ocorrências por dia --}}
        <flux:card class="space-y-3">
            <x-card-heading tone="orange">
                <x-slot:icon><flux:icon.chart-bar class="size-4" /></x-slot:icon>
                {{ __('Ocorrências por dia') }}
            </x-card-heading>
            @if (empty($data['by_day']))
                <flux:text class="text-zinc-500">{{ __('Selecione um intervalo de até 92 dias para ver a série diária.') }}</flux:text>
            @else
                <div wire:key="chart-day-{{ $data['period']['from'] }}-{{ $data['period']['to'] }}" wire:ignore x-data="{
                    init() {
                        new Chart(this.$refs.dayCanvas, {
                            type: 'bar',
                            data: {
                                labels: {{ Js::from(array_map(fn ($r) => \Illuminate\Support\Carbon::parse($r['date'])->format('d/m'), $data['by_day'])) }},
                                datasets: [{ label: 'Ocorrências', data: {{ Js::from(array_column($data['by_day'], 'count')) }}, backgroundColor: '#f6600f', borderRadius: 4 }]
                            },
                            options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true, ticks: { precision: 0 } } } }
                        });
                    }
                }">
                    <div class="h-64"><canvas x-ref="dayCanvas"></canvas></div>
                </div>
            @endif
        </flux:card>

        {{-- Por modalidade (donut) --}}
        <flux:card class="space-y-3">
            <x-card-heading tone="blue">
                <x-slot:icon><flux:icon.chart-pie class="size-4" /></x-slot:icon>
                {{ __('Por modalidade') }}
            </x-card-heading>
            @if (empty($data['by_modality']))
                <flux:text class="text-zinc-500">{{ __('Sem dados no período.') }}</flux:text>
            @else
                <div wire:key="chart-mod-{{ $data['period']['from'] }}-{{ $data['period']['to'] }}-{{ $data['total'] }}" wire:ignore x-data="{
                    init() {
                        new Chart(this.$refs.modCanvas, {
                            type: 'doughnut',
                            data: {
                                labels: {{ Js::from(array_column($data['by_modality'], 'label')) }},
                                datasets: [{ data: {{ Js::from(array_column($data['by_modality'], 'count')) }}, backgroundColor: ['#f6600f','#1c2ba6','#eab308','#22c55e','#0ea5e9','#a855f7','#6b7280'] }]
                            },
                            options: { responsive: true, maintainAspectRatio: false, cutout: '58%', plugins: { legend: { position: 'right' } } }
                        });
                    }
                }">
                    <div class="h-64"><canvas x-ref="modCanvas"></canvas></div>
                </div>
            @endif
        </flux:card>
    </div>

    <div class="grid gap-4 lg:grid-cols-2">
        <flux:card class="space-y-3">
            <x-card-heading tone="blue">
                <x-slot:icon><flux:icon.list-bullet class="size-4" /></x-slot:icon>
                {{ __('Por status') }}
            </x-card-heading>
            <table class="min-w-full divide-y divide-zinc-200 text-sm dark:divide-zinc-700">
                <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800">
                    @forelse ($data['by_status'] as $row)
                        <tr><td class="py-2">{{ $row['label'] }}</td><td class="py-2 text-end font-medium tabular-nums">{{ number_format($row['count'], 0, ',', '.') }}</td></tr>
                    @empty
                        <tr><td class="py-4 text-zinc-500">{{ __('Sem dados.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </flux:card>

        <flux:card class="space-y-3">
            <x-card-heading tone="blue">
                <x-slot:icon><flux:icon.map-pin class="size-4" /></x-slot:icon>
                {{ __('Por município (top 15)') }}
            </x-card-heading>
            <table class="min-w-full divide-y divide-zinc-200 text-sm dark:divide-zinc-700">
                <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800">
                    @forelse ($data['by_municipio'] as $row)
                        <tr><td class="py-2">{{ $row['municipio'] }}</td><td class="py-2 text-end font-medium tabular-nums">{{ number_format($row['count'], 0, ',', '.') }}</td></tr>
                    @empty
                        <tr><td class="py-4 text-zinc-500">{{ __('Sem dados.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </flux:card>
    </div>
</div>
