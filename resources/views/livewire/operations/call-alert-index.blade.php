{{-- Tempo real via Reverb (operations.dispatch). wire:poll longo = fallback se o WebSocket cair. --}}
<div class="cco-page-gap !gap-6" wire:poll.120s>
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <flux:heading size="xl">{{ __('Alertas pendentes') }}</flux:heading>
            <flux:text class="mt-1">{{ __('Chamadas recebidas aguardando decisão na central — monitoramento e conversão em ocorrência.') }}</flux:text>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            <flux:button variant="ghost" icon="radio" :href="route('operations.dispatch')" wire:navigate>
                {{ __('Centro de Controle de Operações') }}
            </flux:button>
            <flux:button variant="ghost" icon="map" :href="route('operations.tactical-map')" wire:navigate>
                {{ __('Mapa tático') }}
            </flux:button>
        </div>
    </div>

    <div class="grid gap-3 sm:grid-cols-3">
        <div class="cco-stat-card">
            <flux:text size="sm" class="text-slate-600 dark:text-slate-400">{{ __('Alertas ativos') }}</flux:text>
            <flux:heading size="xl" class="cco-stat-value">{{ $summary['total_alerts'] }}</flux:heading>
        </div>
        <div class="cco-stat-card">
            <flux:text size="sm" class="text-slate-600 dark:text-slate-400">{{ __('Locais distintos') }}</flux:text>
            <flux:heading size="xl" class="cco-stat-value">{{ $summary['unique_locations'] }}</flux:heading>
        </div>
        <div class="cco-stat-card">
            <flux:text size="sm" class="text-slate-600 dark:text-slate-400">{{ __('Locais alto risco') }}</flux:text>
            <flux:heading size="xl" class="cco-stat-value text-amber-600 dark:text-amber-400">{{ $summary['high_risk_locations'] }}</flux:heading>
        </div>
    </div>

    <flux:card class="space-y-4">
        @if ($rows->isEmpty())
            <div class="flex flex-col items-center gap-3 py-12 text-center">
                <flux:icon.bell-alert class="size-12 text-zinc-300 dark:text-zinc-600" />
                <flux:heading size="lg">{{ __('Nenhum alerta pendente') }}</flux:heading>
                <flux:text>{{ __('Quando o PBX enviar chamadas com localização, elas aparecerão aqui e no mapa tático.') }}</flux:text>
                <flux:button variant="primary" icon="map" :href="route('operations.tactical-map')" wire:navigate class="mt-2">
                    {{ __('Abrir mapa tático') }}
                </flux:button>
            </div>
        @else
            <div class="overflow-x-auto rounded-xl border border-zinc-200 dark:border-zinc-700">
                <table class="min-w-full divide-y divide-zinc-200 text-start text-sm dark:divide-zinc-700">
                    <thead class="bg-zinc-50 dark:bg-zinc-900/50">
                        <tr>
                            <th class="px-4 py-3 font-medium text-zinc-700 dark:text-zinc-300">{{ __('Recebido') }}</th>
                            <th class="px-4 py-3 font-medium text-zinc-700 dark:text-zinc-300">{{ __('Origem / solicitante') }}</th>
                            <th class="px-4 py-3 font-medium text-zinc-700 dark:text-zinc-300">{{ __('Coordenadas') }}</th>
                            <th class="px-4 py-3 font-medium text-zinc-700 dark:text-zinc-300">{{ __('Sensor') }}</th>
                            <th class="px-4 py-3 font-medium text-zinc-700 dark:text-zinc-300">{{ __('Risco') }}</th>
                            <th class="px-4 py-3 font-medium text-zinc-700 dark:text-zinc-300">{{ __('Expira') }}</th>
                            <th class="px-4 py-3 text-end font-medium text-zinc-700 dark:text-zinc-300">{{ __('Ações') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-100 bg-white dark:divide-zinc-800 dark:bg-zinc-900">
                        @foreach ($rows as $row)
                            @php
                                /** @var \App\Models\OperationalCallAlert $alert */
                                $alert = $row['alert'];
                                $risk = $row['risk'] ?? ['level' => 'low', 'label' => __('Baixo'), 'score' => 0];
                                $riskLevel = $risk['level'] ?? 'low';
                                $riskBadgeClass = match ($riskLevel) {
                                    'critical' => 'bg-red-600 text-white dark:bg-red-500',
                                    'high' => 'bg-orange-600 text-white dark:bg-orange-500',
                                    'moderate' => 'bg-amber-500 text-amber-950 dark:bg-amber-400 dark:text-amber-950',
                                    'low' => 'bg-emerald-600 text-white dark:bg-emerald-500',
                                    default => 'bg-zinc-500 text-white',
                                };
                                $receivedAt = $alert->call_received_at ?? $alert->created_at;
                                $meta = is_array($alert->metadata) ? $alert->metadata : [];
                                $temp = $alert->resolvedTemperature();
                                $humidity = isset($meta['humidity']) ? (float) $meta['humidity'] : null;
                                $wind = isset($meta['wind_speed']) ? (float) $meta['wind_speed'] : null;
                            @endphp
                            <tr wire:key="call-alert-{{ $alert->id }}" class="hover:bg-zinc-50/80 dark:hover:bg-zinc-800/40">
                                <td class="whitespace-nowrap px-4 py-3 tabular-nums text-zinc-600 dark:text-zinc-400">
                                    {{ $receivedAt?->timezone(config('app.timezone'))->format('d/m/Y H:i') ?? '—' }}
                                    @if ($row['stacked_count'] > 1)
                                        <flux:badge size="sm" color="amber" class="ml-1" inset>{{ $row['stacked_count'] }}×</flux:badge>
                                    @endif
                                </td>
                                <td class="px-4 py-3">
                                    <div class="font-medium">{{ $alert->caller_name ?? '—' }}</div>
                                    <div class="text-xs text-zinc-500">
                                        {{ $alert->phone ?? '—' }}
                                        @if ($alert->external_reference)
                                            · {{ $alert->external_reference }}
                                        @endif
                                    </div>
                                </td>
                                <td class="whitespace-nowrap px-4 py-3 font-mono text-xs text-zinc-600 dark:text-zinc-400">
                                    {{ number_format((float) $alert->latitude, 5, ',', '.') }},
                                    {{ number_format((float) $alert->longitude, 5, ',', '.') }}
                                </td>
                                <td class="px-4 py-3 text-xs text-zinc-600 dark:text-zinc-400">
                                    @if ($temp !== null)
                                        <span>{{ __('Temp.') }} {{ number_format($temp, 1, ',', '.') }}</span>
                                    @endif
                                    @if ($humidity !== null)
                                        <span class="ml-1">· {{ number_format($humidity, 0, ',', '.') }}% {{ __('UR') }}</span>
                                    @endif
                                    @if ($wind !== null)
                                        <span class="ml-1">· {{ number_format($wind, 1, ',', '.') }} km/h</span>
                                    @endif
                                    @if ($temp === null && $humidity === null && $wind === null)
                                        —
                                    @endif
                                </td>
                                <td class="px-4 py-3">
                                    <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold {{ $riskBadgeClass }}">
                                        {{ $risk['label'] ?? __('Risco') }}
                                    </span>
                                </td>
                                <td class="whitespace-nowrap px-4 py-3 tabular-nums text-zinc-600 dark:text-zinc-400">
                                    {{ $alert->expires_at->timezone(config('app.timezone'))->format('d/m/Y H:i') }}
                                </td>
                                <td class="whitespace-nowrap px-4 py-3 text-end">
                                    <div class="flex flex-wrap items-center justify-end gap-1">
                                        <flux:button
                                            size="sm"
                                            variant="ghost"
                                            wire:click="openFireReport('{{ $row['location_key'] }}')"
                                        >
                                            {{ __('Detalhes') }}
                                        </flux:button>
                                        <flux:button
                                            size="sm"
                                            variant="danger"
                                            wire:click="abortAlert('{{ $row['location_key'] }}')"
                                            wire:confirm="{{ __('Abortar todos os alertas neste local?') }}"
                                        >
                                            {{ __('Abortar') }}
                                        </flux:button>
                                        <flux:button
                                            size="sm"
                                            variant="primary"
                                            wire:click="createIncidentFromAlert('{{ $alert->id }}')"
                                        >
                                            {{ __('Ocorrência') }}
                                        </flux:button>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </flux:card>
</div>
