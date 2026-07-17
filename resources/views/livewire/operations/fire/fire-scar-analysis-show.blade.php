<div class="cco-page-gap">
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div class="flex items-center gap-3">
            <span class="flex size-11 shrink-0 items-center justify-center rounded-xl bg-orange-50 text-orange-600 ring-1 ring-orange-100 dark:bg-orange-900/25 dark:text-orange-400 dark:ring-orange-900/40">
                <flux:icon.fire class="size-6" />
            </span>
            <div>
                <flux:heading size="xl" class="tracking-tight text-slate-800 dark:text-slate-100">{{ __('Cicatriz de incêndio') }}</flux:heading>
                <flux:text class="mt-0.5 text-slate-600 dark:text-slate-400">
                    {{ $analysis->municipio ?? '—' }}{{ $analysis->estado ? '/'.$analysis->estado : '' }}
                    · {{ number_format((float) $analysis->latitude, 5) }}, {{ number_format((float) $analysis->longitude, 5) }}
                </flux:text>
            </div>
        </div>
        <div class="flex items-center gap-2">
            <flux:badge :color="$analysis->status->color()">{{ $analysis->status->label() }}</flux:badge>
            @if ($analysis->status->value === 'CONCLUIDO')
                <flux:button variant="primary" color="orange" icon="arrow-down-tray" :href="route('operations.reports.fire-scars.document', $analysis)" target="_blank">{{ __('PDF') }}</flux:button>
            @endif
            <flux:button variant="ghost" icon="arrow-left" :href="route('operations.reports.fire-scars.index')" wire:navigate>{{ __('Voltar') }}</flux:button>
        </div>
    </div>

    @if ($analysis->status->value === 'ERRO')
        <flux:callout variant="danger">{{ $analysis->error_message ?? __('Falha ao processar a análise.') }}</flux:callout>
    @elseif (! $analysis->status->isTerminal())
        <flux:callout variant="secondary" wire:poll.6s>{{ __('Processando análise no serviço externo…') }}</flux:callout>
    @endif

    <div class="grid gap-4 lg:grid-cols-3">
        <flux:card class="space-y-4 lg:col-span-2">
            <x-card-heading tone="orange">
                <x-slot:icon><flux:icon.map class="size-4" /></x-slot:icon>
                {{ __('Área queimada') }}
            </x-card-heading>

            <div
                wire:ignore
                x-data="fireScarMap({
                    lat: {{ (float) $analysis->latitude }},
                    lng: {{ (float) $analysis->longitude }},
                    geojson: @js($analysis->geometry_geojson),
                    color: '{{ $severity?->color() ?? '#e34a33' }}',
                })"
            >
                <div x-ref="scarMapEl" class="h-96 w-full overflow-hidden rounded-xl border border-zinc-200 dark:border-zinc-700"></div>
            </div>

            <div class="grid grid-cols-2 gap-4 sm:grid-cols-3">
                <div>
                    <flux:text size="sm" class="text-zinc-500">{{ __('Área') }}</flux:text>
                    <div class="text-lg font-semibold">{{ $analysis->area_ha !== null ? number_format((float) $analysis->area_ha, 2, ',', '.').' ha' : '—' }}</div>
                </div>
                <div>
                    <flux:text size="sm" class="text-zinc-500">{{ __('Perímetro') }}</flux:text>
                    <div class="text-lg font-semibold">{{ $analysis->perimeter_km !== null ? number_format((float) $analysis->perimeter_km, 2, ',', '.').' km' : '—' }}</div>
                </div>
                <div>
                    <flux:text size="sm" class="text-zinc-500">{{ __('Severidade') }}</flux:text>
                    <div class="text-lg font-semibold">
                        @if ($severity)
                            <span class="inline-flex items-center gap-1.5">
                                <span class="inline-block size-3 rounded-full" style="background: {{ $severity->color() }}"></span>
                                {{ $severity->label() }}
                            </span>
                        @else — @endif
                    </div>
                </div>
            </div>
        </flux:card>

        <flux:card class="space-y-3">
            <x-card-heading tone="blue">
                <x-slot:icon><flux:icon.chart-bar class="size-4" /></x-slot:icon>
                {{ __('Índices espectrais') }}
            </x-card-heading>

            <dl class="divide-y divide-zinc-100 text-sm dark:divide-zinc-800">
                @foreach ([
                    'NBR pré' => $analysis->nbr_pre,
                    'NBR pós' => $analysis->nbr_post,
                    'dNBR' => $analysis->dnbr,
                    'RBR' => $analysis->rbr,
                    'NDVI pré' => $analysis->ndvi_pre,
                    'NDVI pós' => $analysis->ndvi_post,
                    'BAI' => $analysis->bai,
                ] as $label => $value)
                    <div class="flex items-center justify-between py-2">
                        <dt class="text-zinc-500">{{ $label }}</dt>
                        <dd class="font-medium">{{ $value !== null ? number_format((float) $value, 4, ',', '.') : '—' }}</dd>
                    </div>
                @endforeach
            </dl>

            <flux:separator />

            <dl class="space-y-2 text-sm">
                <div class="flex justify-between"><dt class="text-zinc-500">{{ __('Sensor') }}</dt><dd class="font-medium">{{ $analysis->sensor ?? '—' }}</dd></div>
                <div class="flex justify-between"><dt class="text-zinc-500">{{ __('Confiança') }}</dt><dd class="font-medium">{{ $analysis->confidence ?? '—' }}</dd></div>
                <div class="flex justify-between"><dt class="text-zinc-500">{{ __('Bioma') }}</dt><dd class="font-medium">{{ $analysis->bioma ?? '—' }}</dd></div>
                <div class="flex justify-between"><dt class="text-zinc-500">{{ __('Vegetação') }}</dt><dd class="font-medium">{{ $analysis->vegetation_type ?? '—' }}</dd></div>
                <div class="flex justify-between"><dt class="text-zinc-500">{{ __('Pré/pós-fogo') }}</dt><dd class="font-medium">{{ $analysis->pre_fire_date?->format('d/m/Y') ?? '—' }} → {{ $analysis->post_fire_date?->format('d/m/Y') ?? '—' }}</dd></div>
                @if ($analysis->incident)
                    <div class="flex justify-between">
                        <dt class="text-zinc-500">{{ __('Ocorrência') }}</dt>
                        <dd class="font-medium">
                            <a class="text-blue-600 hover:underline" href="{{ route('operations.incidents.show', $analysis->incident) }}" wire:navigate>
                                {{ $analysis->incident->talao ?? __('s/ talão') }}/{{ $analysis->incident->dispatch_year }}
                            </a>
                        </dd>
                    </div>
                @endif
            </dl>

            @if ($analysis->notes)
                <flux:separator />
                <flux:text size="sm" class="text-zinc-500">{{ $analysis->notes }}</flux:text>
            @endif
        </flux:card>
    </div>
</div>
