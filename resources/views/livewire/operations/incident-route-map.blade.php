<div>
    <div
        wire:ignore
        x-data="incidentRouteMap({
            routeUrl: '{{ route('operations.incidents.route', $incident) }}',
            incidentLat: '{{ $incident->latitude }}',
            incidentLng: '{{ $incident->longitude }}',
            hasDevice: {{ $hasDevice ? 'true' : 'false' }},
            vehiclePrefix: @js($routeVehicle?->prefix)
        })"
        @incident-route-map-refresh.window="reloadRoute()"
        style="position:fixed; top:3rem; left:0; right:0; bottom:0; z-index:0"
        data-test="incident-route-map-fullscreen"
    >
        <div x-ref="routeMapEl" style="width:100%; height:100%"></div>

        <div
            x-show="state === 'loading'"
            class="pointer-events-none absolute inset-0 flex items-center justify-center bg-white/70 dark:bg-zinc-900/70"
        >
            <flux:text class="text-zinc-500">{{ __('Carregando percurso registrado…') }}</flux:text>
        </div>

        <div
            x-show="state === 'empty'"
            class="pointer-events-none absolute inset-0 flex flex-col items-center justify-center bg-white/80 dark:bg-zinc-900/80"
        >
            <flux:icon.map-pin class="size-8 text-zinc-400" />
            <flux:text class="mt-2 text-zinc-500">{{ __('Sem pontos de rota no intervalo da ocorrência.') }}</flux:text>
        </div>

        <div
            x-show="state === 'no_device'"
            class="pointer-events-none absolute inset-0 flex flex-col items-center justify-center bg-white/80 dark:bg-zinc-900/80"
        >
            <flux:icon.signal-slash class="size-8 text-zinc-400" />
            <flux:text class="mt-2 text-zinc-500">{{ __('Viatura sem device Traccar vinculado.') }}</flux:text>
        </div>

        <div
            x-show="state === 'error'"
            class="pointer-events-none absolute inset-0 flex flex-col items-center justify-center bg-white/80 dark:bg-zinc-900/80"
        >
            <flux:icon.exclamation-triangle class="size-8 text-amber-400" />
            <flux:text class="mt-2 text-center text-zinc-500" x-text="errorMessage || @js(__('Não foi possível carregar a rota.'))"></flux:text>
        </div>
    </div>

    <div
        class="w-72 rounded-xl border border-slate-200/80 bg-white/90 shadow-lg backdrop-blur-sm dark:border-slate-700/60 dark:bg-slate-900/90"
        style="position:fixed; top:calc(3rem + 0.75rem); left:0.75rem; z-index:10"
        data-test="incident-route-map-fullscreen-panel"
    >
        <div class="space-y-2 p-3">
            <p class="text-[10px] font-semibold uppercase tracking-widest text-slate-400 dark:text-slate-500">{{ __('Ocorrência') }}</p>
            <p class="text-sm font-semibold text-slate-800 dark:text-slate-100">
                {{ __('Talão') }} {{ $incident->dispatch_year }}/{{ $incident->talao }}
            </p>
            @if ($routeContext->from && $routeContext->to)
                <p class="text-xs text-slate-500 dark:text-slate-400">
                    {{ __('Intervalo do percurso') }}:
                    {{ $routeContext->from->format('d/m H:i') }}
                    —
                    {{ $routeContext->to->format('d/m H:i') }}
                </p>
            @endif
            @if ($routeContext->isHistoricalReplay())
                <p class="text-xs text-slate-500 dark:text-slate-400">
                    {{ __('Replay do trajeto registrado no atendimento.') }}
                </p>
            @endif
            @if ($hasDevice)
                <p class="text-xs text-slate-500 dark:text-slate-400">
                    {{ $routeVehicle->prefix ?? '' }} · Device {{ $routeVehicle->device_id }}
                </p>
            @elseif ($routeVehicle)
                <p class="text-xs text-amber-600">{{ __('Viatura sem device Traccar') }}</p>
            @elseif ($routeContext->dispatch)
                <p class="text-xs text-amber-600">{{ __('Viatura do atendimento sem device Traccar') }}</p>
            @else
                <p class="text-xs text-slate-500 dark:text-slate-400">{{ __('Sem registro de viatura nesta ocorrência') }}</p>
            @endif

            @if ($routeContext->canFetchRoute())
                <flux:button
                    type="button"
                    size="sm"
                    variant="primary"
                    icon="arrow-path"
                    class="w-full"
                    x-data
                    x-on:click="window.dispatchEvent(new CustomEvent('incident-route-map-refresh'))"
                >
                    {{ __('Recarregar percurso') }}
                </flux:button>
            @endif
        </div>

        <div class="mx-3 border-t border-slate-100 dark:border-slate-700/60"></div>

        <div class="space-y-1 p-3">
            <p class="text-[10px] font-semibold uppercase tracking-widest text-slate-400 dark:text-slate-500">{{ __('Legenda') }}</p>
            <div class="flex items-center gap-2 text-[11px] text-slate-600 dark:text-slate-300">
                <span class="inline-block h-2.5 w-2.5 shrink-0 rounded-full bg-red-500"></span>
                {{ __('Local da ocorrência') }}
            </div>
            <div class="flex items-center gap-2 text-[11px] text-slate-600 dark:text-slate-300">
                <span class="inline-block h-2.5 w-2.5 shrink-0 rounded-full bg-green-500"></span>
                {{ __('Saída da base') }}
            </div>
            <div class="flex items-center gap-2 text-[11px] text-slate-600 dark:text-slate-300">
                <span class="inline-block h-3 w-5 shrink-0 rounded bg-blue-500"></span>
                {{ __('Percurso') }}
            </div>
            <div class="flex items-center gap-2 text-[11px] text-slate-600 dark:text-slate-300">
                <span class="inline-block h-2.5 w-2.5 shrink-0 rounded-full bg-amber-400"></span>
                {{ __('Último ponto') }}
            </div>
        </div>
    </div>
</div>
