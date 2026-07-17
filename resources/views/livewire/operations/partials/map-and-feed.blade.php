@php
    use App\Support\Operations\TimelineEventLabels;
    $incidentUrlTemplate = url('/operations/incidents/__ID__');
@endphp

<div class="grid gap-4 xl:grid-cols-2">

    {{-- ── Mapa tático ──────────────────────────────────────────────────── --}}
    <flux:card class="flex min-h-[22rem] flex-col gap-3">

        {{-- Cabeçalho + legenda — atualizados em tempo real via Reverb (fallback no poll longo) --}}
        <div class="flex flex-wrap items-center justify-between gap-2">
            <flux:subheading>{{ __('Mapa tático') }}</flux:subheading>
            <div class="flex flex-wrap items-center gap-3 text-xs text-zinc-500">
                <span class="inline-flex items-center gap-1">
                    <span class="inline-block h-2.5 w-2.5 rounded-full bg-red-500"></span>{{ __('Ocorrência aberta') }}
                </span>
                <span class="inline-flex items-center gap-1">
                    <span class="inline-block h-2.5 w-2.5 rounded-full bg-blue-600"></span>{{ __('Despachada') }}
                </span>
                <span class="inline-flex items-center gap-1">
                    <span class="inline-block h-2.5 w-2.5 rounded-full bg-green-500"></span>{{ __('Viatura em atendimento') }}
                </span>
                <span class="inline-flex items-center gap-1">
                    <span class="inline-block h-2.5 w-2.5 rounded-full bg-slate-400"></span>{{ __('Viatura na base') }}
                </span>
            </div>
        </div>

        {{-- Contadores — fora do wire:ignore, atualizados pelo poll --}}
        <div class="flex flex-wrap gap-2 text-xs text-zinc-500">
            @if ($mapIncidents->isNotEmpty())
                <span class="rounded-md border border-zinc-200 bg-zinc-50 px-2 py-0.5 dark:border-zinc-700 dark:bg-zinc-800">
                    {{ $mapIncidents->count() }} {{ __('ocorrência(s) no mapa') }}
                </span>
            @endif
            @if ($mapVehicles->isNotEmpty())
                <span class="rounded-md border border-zinc-200 bg-zinc-50 px-2 py-0.5 dark:border-zinc-700 dark:bg-zinc-800">
                    {{ $mapVehicles->count() }} {{ __('viatura(s) rastreada(s)') }}
                </span>
            @endif
            @if ($mapIncidents->isEmpty() && $mapVehicles->isEmpty())
                <span class="text-zinc-400">{{ __('Sem coordenadas disponíveis') }}</span>
            @endif
        </div>

        {{--
            wire:ignore — Livewire NÃO toca neste bloco durante re-renders.
            O Leaflet inicializa uma vez e persiste. Atualizações vêm via Reverb:
            - vehicle.position.updated  → move marcador de viatura
            - incident.created          → adiciona marcador vermelho
            - unit.dispatched           → muda marcador para azul
            - unit.released             → remove viatura empenhada e ocorrência do mapa
        --}}
        <div
            wire:ignore
            x-data="dispatchMap({
                incidents:       {{ Js::from($mapIncidents) }},
                vehicles:        {{ Js::from($mapVehicles) }},
                incidentUrlBase: '{{ $incidentUrlTemplate }}',
                vehiclesUrl:     '{{ route('operations.map.vehicles') }}',
                municipiosUrl:   '{{ route('operations.cadastro.bases.geojson') }}'
            })"
            class="relative flex flex-1 flex-col"
        >
            <div
                x-ref="dispatchMapEl"
                class="min-h-[18rem] flex-1 rounded-xl border border-zinc-200 bg-zinc-100 dark:border-zinc-700 dark:bg-zinc-800"
                style="z-index:0"
            ></div>
        </div>
    </flux:card>

    {{-- ── Feed de eventos ──────────────────────────────────────────────── --}}
    <flux:card class="flex flex-col gap-3">
        <flux:subheading>{{ __('Últimos eventos operacionais') }}</flux:subheading>
        @if ($recentTimeline->isEmpty())
            <flux:text size="sm">{{ __('Nenhum evento registrado ainda.') }}</flux:text>
        @else
            <ul class="max-h-[22rem] space-y-3 overflow-y-auto pe-1">
                @foreach ($recentTimeline as $event)
                    <li wire:key="tl-{{ $event->id }}" class="border-s-2 border-blue-500 ps-3 dark:border-blue-400">
                        <div class="flex flex-wrap items-baseline justify-between gap-2">
                            <flux:text class="font-medium">{{ TimelineEventLabels::for($event->event_key) }}</flux:text>
                            <flux:text size="sm" class="tabular-nums text-zinc-500">{{ $event->recorded_at->format('d/m H:i:s') }}</flux:text>
                        </div>
                        @if ($event->incident)
                            <flux:link class="text-sm" :href="route('operations.incidents.show', $event->incident)" wire:navigate>
                                {{ __('Talão') }} {{ $event->incident->talao }}/{{ $event->incident->dispatch_year }}
                            </flux:link>
                        @endif
                        @if ($event->actor)
                            <flux:text size="sm" class="text-zinc-500">{{ __('Por') }} {{ $event->actor->name }}</flux:text>
                        @endif
                    </li>
                @endforeach
            </ul>
        @endif
    </flux:card>
</div>
