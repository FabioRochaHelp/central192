<div>
    <flux:modal wire:model.self="showModal" wire:close="close" class="max-w-4xl max-h-[92vh] w-full overflow-y-auto">
        @if ($showModal && ! empty($report))
            @php
                $risk = $report['risk'] ?? ['level' => 'low', 'label' => __('Baixo'), 'score' => 0];
                $riskLevel = $risk['level'] ?? 'low';
                $riskBadgeClass = match ($riskLevel) {
                    'critical' => 'bg-red-600 text-white dark:bg-red-500',
                    'high' => 'bg-orange-600 text-white dark:bg-orange-500',
                    'moderate' => 'bg-amber-500 text-amber-950 dark:bg-amber-400 dark:text-amber-950',
                    'low' => 'bg-emerald-600 text-white dark:bg-emerald-500',
                    default => 'bg-zinc-500 text-white',
                };
                $charts = $report['charts'] ?? [];
                $windCompass = $report['wind_compass'] ?? ['degrees' => null, 'label' => null, 'rotation' => 0];
                $alertCount = (int) ($report['alert_count'] ?? 0);
                $period = $report['period'] ?? ['from' => null, 'to' => null];
            @endphp

            @assets
                <script src="https://cdn.jsdelivr.net/npm/chart.js@4/dist/chart.umd.min.js"></script>
            @endassets

            <div class="space-y-6 pe-10" wire:key="fire-report-{{ $report['location_key'] ?? 'none' }}">
                <div class="space-y-4 border-b border-slate-200/90 pb-4 dark:border-slate-700/60">
                    <flux:heading size="lg">{{ __('Análise do foco') }}</flux:heading>

                    <div class="flex flex-wrap items-start justify-between gap-4">
                        <div class="min-w-0 flex-1 space-y-1">
                            <div class="flex flex-wrap gap-x-4 gap-y-1 text-sm text-zinc-600 dark:text-zinc-400">
                            @if (! empty($report['reference']))
                                <span>
                                    <span class="font-medium text-zinc-700 dark:text-zinc-300">{{ __('Referência') }}:</span>
                                    {{ $report['reference'] }}
                                </span>
                            @endif
                            <span>
                                <span class="font-medium text-zinc-700 dark:text-zinc-300">{{ __('Alertas') }}:</span>
                                {{ $alertCount }}
                            </span>
                            @if (! empty($period['from']) || ! empty($period['to']))
                                <span>
                                    <span class="font-medium text-zinc-700 dark:text-zinc-300">{{ __('Período') }}:</span>
                                    {{ $period['from'] ?? '—' }} — {{ $period['to'] ?? '—' }}
                                </span>
                            @endif
                            @if (! empty($report['caller_name']))
                                <span>
                                    <span class="font-medium text-zinc-700 dark:text-zinc-300">{{ __('Solicitante') }}:</span>
                                    {{ $report['caller_name'] }}
                                </span>
                            @endif
                            </div>
                        </div>

                        <div class="flex shrink-0 flex-col items-end gap-1 sm:me-2">
                            <span class="inline-flex items-center rounded-full px-3 py-1 text-sm font-semibold {{ $riskBadgeClass }}">
                                {{ $risk['label'] ?? __('Risco') }}
                            </span>
                            <span class="text-xs tabular-nums text-zinc-500">
                                {{ __('Pontuação') }}: {{ (int) ($risk['score'] ?? 0) }}/100
                            </span>
                        </div>
                    </div>
                </div>

                @if (! empty($report['metrics']))
                    <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                        @foreach ($report['metrics'] as $metric)
                            <flux:card class="space-y-1 p-4">
                                <flux:text class="text-xs font-medium uppercase tracking-wide text-zinc-500">
                                    {{ $metric['label'] }}
                                </flux:text>
                                <p class="text-lg font-semibold tabular-nums text-zinc-900 dark:text-zinc-50">
                                    {{ $metric['value'] }}
                                </p>
                                @if (! empty($metric['hint']))
                                    <flux:text class="text-xs text-zinc-500">{{ $metric['hint'] }}</flux:text>
                                @endif
                            </flux:card>
                        @endforeach
                    </div>
                @endif

                @if (! empty($charts['labels']))
                    <div class="grid gap-4 lg:grid-cols-2">
                        <flux:card class="space-y-3 p-4">
                            <flux:heading size="sm">{{ __('Temperatura radiativa') }}</flux:heading>
                            <div wire:ignore class="h-48"
                                x-data="{
                                    init() {
                                        new Chart(this.$refs.canvas, {
                                            type: 'line',
                                            data: {
                                                labels: {{ Js::from($charts['labels'] ?? []) }},
                                                datasets: [{
                                                    label: {{ Js::from(__('Temperatura')) }},
                                                    data: {{ Js::from($charts['temperature'] ?? []) }},
                                                    borderColor: '#ef4444',
                                                    backgroundColor: 'rgba(239, 68, 68, 0.1)',
                                                    fill: true,
                                                    tension: 0.3,
                                                    spanGaps: true,
                                                }]
                                            },
                                            options: {
                                                responsive: true,
                                                maintainAspectRatio: false,
                                                plugins: { legend: { display: false } },
                                                scales: {
                                                    y: { beginAtZero: false },
                                                    x: { ticks: { maxRotation: 45, minRotation: 0 } }
                                                }
                                            }
                                        });
                                    }
                                }">
                                <canvas x-ref="canvas"></canvas>
                            </div>
                        </flux:card>

                        <flux:card class="space-y-3 p-4">
                            <flux:heading size="sm">{{ __('Velocidade do vento') }}</flux:heading>
                            <div wire:ignore class="h-48"
                                x-data="{
                                    init() {
                                        new Chart(this.$refs.canvas, {
                                            type: 'bar',
                                            data: {
                                                labels: {{ Js::from($charts['labels'] ?? []) }},
                                                datasets: [{
                                                    label: {{ Js::from(__('km/h')) }},
                                                    data: {{ Js::from($charts['wind_speed'] ?? []) }},
                                                    backgroundColor: 'rgba(59, 130, 246, 0.7)',
                                                    borderRadius: 4,
                                                }]
                                            },
                                            options: {
                                                responsive: true,
                                                maintainAspectRatio: false,
                                                plugins: { legend: { display: false } },
                                                scales: {
                                                    y: { beginAtZero: true },
                                                    x: { ticks: { maxRotation: 45, minRotation: 0 } }
                                                }
                                            }
                                        });
                                    }
                                }">
                                <canvas x-ref="canvas"></canvas>
                            </div>
                        </flux:card>

                        <flux:card class="space-y-3 p-4 lg:col-span-2">
                            <flux:heading size="sm">{{ __('Umidade relativa') }}</flux:heading>
                            <div wire:ignore class="h-48"
                                x-data="{
                                    init() {
                                        new Chart(this.$refs.canvas, {
                                            type: 'line',
                                            data: {
                                                labels: {{ Js::from($charts['labels'] ?? []) }},
                                                datasets: [{
                                                    label: {{ Js::from(__('%')) }},
                                                    data: {{ Js::from($charts['humidity'] ?? []) }},
                                                    borderColor: '#0ea5e9',
                                                    backgroundColor: 'rgba(14, 165, 233, 0.1)',
                                                    fill: true,
                                                    tension: 0.3,
                                                    spanGaps: true,
                                                }]
                                            },
                                            options: {
                                                responsive: true,
                                                maintainAspectRatio: false,
                                                plugins: { legend: { display: false } },
                                                scales: {
                                                    y: { beginAtZero: true, max: 100 },
                                                    x: { ticks: { maxRotation: 45, minRotation: 0 } }
                                                }
                                            }
                                        });
                                    }
                                }">
                                <canvas x-ref="canvas"></canvas>
                            </div>
                        </flux:card>
                    </div>
                @endif

                <flux:card class="flex flex-col items-center gap-3 p-6 sm:flex-row sm:justify-center">
                    <flux:heading size="sm" class="w-full text-center sm:w-auto sm:text-start">
                        {{ __('Direção do vento') }}
                    </flux:heading>
                    <div class="relative size-36 shrink-0 rounded-full border-2 border-zinc-200 bg-zinc-50 dark:border-zinc-600 dark:bg-zinc-800">
                        <span class="absolute left-1/2 top-1 -translate-x-1/2 text-xs font-bold text-zinc-500">N</span>
                        <span class="absolute right-1 top-1/2 -translate-y-1/2 text-xs font-bold text-zinc-500">E</span>
                        <span class="absolute bottom-1 left-1/2 -translate-x-1/2 text-xs font-bold text-zinc-500">S</span>
                        <span class="absolute left-1 top-1/2 -translate-y-1/2 text-xs font-bold text-zinc-500">O</span>
                        <div
                            class="absolute inset-4 flex items-center justify-center"
                            style="transform: rotate({{ (float) ($windCompass['rotation'] ?? 0) }}deg);"
                        >
                            <div class="h-0 w-0 border-x-[10px] border-x-transparent border-b-[48px] border-b-sky-600 dark:border-b-sky-400"></div>
                        </div>
                        <div class="absolute inset-0 flex items-center justify-center">
                            <span class="mt-8 text-center text-xs font-medium text-zinc-700 dark:text-zinc-300">
                                {{ $windCompass['label'] ?? '—' }}
                                @if ($windCompass['degrees'] !== null)
                                    <span class="tabular-nums text-zinc-500">
                                        {{ number_format((float) $windCompass['degrees'], 0, ',', '.') }}°
                                    </span>
                                @endif
                            </span>
                        </div>
                    </div>
                </flux:card>

                @if (! empty($report['observations']))
                    <div class="space-y-3">
                        <flux:heading size="sm">{{ __('Observações') }}</flux:heading>
                        <ul class="space-y-2">
                            @foreach ($report['observations'] as $observation)
                                @php
                                    $obsLevel = $observation['level'] ?? 'info';
                                    $obsClass = match ($obsLevel) {
                                        'high' => 'border-red-300/80 bg-red-50 dark:border-red-800/60 dark:bg-red-950/40',
                                        'medium' => 'border-amber-300/80 bg-amber-50 dark:border-amber-800/60 dark:bg-amber-950/40',
                                        'info' => 'border-sky-300/80 bg-sky-50 dark:border-sky-800/60 dark:bg-sky-950/40',
                                        default => 'border-zinc-200 bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-800/50',
                                    };
                                @endphp
                                <li class="rounded-lg border px-4 py-3 {{ $obsClass }}">
                                    <p class="text-sm font-semibold text-zinc-900 dark:text-zinc-100">
                                        {{ $observation['title'] }}
                                    </p>
                                    <p class="mt-1 text-sm text-zinc-600 dark:text-zinc-400">
                                        {{ $observation['text'] }}
                                    </p>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                @if ($alertCount > 1 && ! empty($report['series']))
                    <div class="space-y-3">
                        <flux:heading size="sm">{{ __('Histórico de leituras') }}</flux:heading>
                        <div class="overflow-x-auto rounded-lg border border-zinc-200 dark:border-zinc-700">
                            <table class="min-w-full divide-y divide-zinc-200 text-sm dark:divide-zinc-700">
                                <thead class="bg-zinc-50 dark:bg-zinc-800/80">
                                    <tr>
                                        <th class="px-3 py-2 text-start font-medium text-zinc-600 dark:text-zinc-400">{{ __('Data/hora') }}</th>
                                        <th class="px-3 py-2 text-start font-medium text-zinc-600 dark:text-zinc-400">{{ __('Referência') }}</th>
                                        <th class="px-3 py-2 text-end font-medium text-zinc-600 dark:text-zinc-400">{{ __('Temp.') }}</th>
                                        <th class="px-3 py-2 text-end font-medium text-zinc-600 dark:text-zinc-400">{{ __('Umidade') }}</th>
                                        <th class="px-3 py-2 text-end font-medium text-zinc-600 dark:text-zinc-400">{{ __('Vento') }}</th>
                                        <th class="px-3 py-2 text-end font-medium text-zinc-600 dark:text-zinc-400">{{ __('Dir.') }}</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-zinc-200 bg-white dark:divide-zinc-700 dark:bg-zinc-900/50">
                                    @foreach ($report['series'] as $row)
                                        <tr>
                                            <td class="whitespace-nowrap px-3 py-2 tabular-nums text-zinc-900 dark:text-zinc-100">
                                                {{ $row['label'] ?? '—' }}
                                            </td>
                                            <td class="max-w-[8rem] truncate px-3 py-2 text-zinc-600 dark:text-zinc-400">
                                                {{ $row['reference'] ?? '—' }}
                                            </td>
                                            <td class="whitespace-nowrap px-3 py-2 text-end tabular-nums text-zinc-700 dark:text-zinc-300">
                                                @if (isset($row['temperature']) && $row['temperature'] !== null)
                                                    {{ number_format((float) $row['temperature'], 1, ',', '.') }}
                                                @else
                                                    —
                                                @endif
                                            </td>
                                            <td class="whitespace-nowrap px-3 py-2 text-end tabular-nums text-zinc-700 dark:text-zinc-300">
                                                @if (isset($row['humidity']) && $row['humidity'] !== null)
                                                    {{ number_format((float) $row['humidity'], 1, ',', '.') }}%
                                                @else
                                                    —
                                                @endif
                                            </td>
                                            <td class="whitespace-nowrap px-3 py-2 text-end tabular-nums text-zinc-700 dark:text-zinc-300">
                                                @if (isset($row['wind_speed']) && $row['wind_speed'] !== null)
                                                    {{ number_format((float) $row['wind_speed'], 1, ',', '.') }}
                                                @else
                                                    —
                                                @endif
                                            </td>
                                            <td class="whitespace-nowrap px-3 py-2 text-end tabular-nums text-zinc-700 dark:text-zinc-300">
                                                @if (! empty($row['wind_direction_text']))
                                                    {{ $row['wind_direction_text'] }}
                                                @elseif (isset($row['wind_direction']) && $row['wind_direction'] !== null)
                                                    {{ number_format((float) $row['wind_direction'], 0, ',', '.') }}°
                                                @else
                                                    —
                                                @endif
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                @endif

                <div class="flex justify-end border-t border-slate-200/90 pt-4 dark:border-slate-700/60">
                    <flux:button variant="ghost" wire:click="close">{{ __('Fechar') }}</flux:button>
                </div>
            </div>
        @endif
    </flux:modal>
</div>
