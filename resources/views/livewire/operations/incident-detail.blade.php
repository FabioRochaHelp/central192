@php
    use App\Domain\Operations\Enums\IncidentStatus;
    use App\Models\Prescription;
    use App\Support\Operations\TimelineEventLabels;
    use App\Support\Operations\TimelineEventPayloadFormatter;
    $modality = $incident->nature?->report_modality;
@endphp

{{-- Tempo real via Reverb. wire:poll longo = fallback se o WebSocket cair. --}}
<div class="cco-page-gap" wire:poll.120s="refreshOperationalState">
    <div class="flex flex-wrap items-center gap-3">
        <flux:button variant="ghost" icon="arrow-left" :href="route('operations.dispatch')" wire:navigate>{{ __('Voltar ao CCO') }}</flux:button>
        <flux:button variant="ghost" icon="rectangle-stack" :href="route('operations.incidents.index')" wire:navigate>{{ __('Lista de ocorrências') }}</flux:button>
    </div>

    <div class="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
        <div>
            <flux:heading size="xl" class="tabular-nums">
                {{ __('Ocorrência') }} #{{ $incident->talao }}/{{ $incident->dispatch_year }}
            </flux:heading>
            <flux:text class="mt-1">{{ $incident->occurred_at->format('d/m/Y H:i:s') }}</flux:text>
        </div>
        <div class="flex flex-wrap items-center justify-end gap-2">
            <x-incident.status-badge :status="$incident->status" size="lg" />
            <x-incident.manchester-badge :risk="$incident->manchester_risk" size="lg" />
        </div>
    </div>

    <div class="grid gap-4 lg:grid-cols-3">
        <flux:card class="space-y-2 lg:col-span-2">
            <flux:subheading>{{ __('Endereço e referência') }}</flux:subheading>
            <flux:text>{{ trim(implode(', ', array_filter([$incident->address_line, $incident->number, $incident->district, $incident->city]))) ?: '—' }}</flux:text>
            @if ($incident->reference_notes)
                <flux:text size="sm" class="text-zinc-600 dark:text-zinc-400">{{ $incident->reference_notes }}</flux:text>
            @endif
            @if ($incident->latitude && $incident->longitude)
                <flux:text size="sm" class="font-mono text-zinc-500">{{ $incident->latitude }}, {{ $incident->longitude }}</flux:text>
            @endif
        </flux:card>
        <flux:card class="space-y-2">
            <flux:subheading>{{ __('Natureza e solicitante') }}</flux:subheading>
            <flux:text>{{ $incident->nature?->name ?? '—' }}</flux:text>
            <flux:text size="sm">{{ $incident->caller_name ?? '—' }} · {{ $incident->caller_phone ?? '—' }}</flux:text>
        </flux:card>
    </div>

    <flux:card class="space-y-4">
        <flux:subheading>{{ __('Descrição') }}</flux:subheading>

        @if ($incident->description)
            <flux:text class="whitespace-pre-wrap">{{ $incident->description }}</flux:text>
        @else
            <flux:text size="sm" class="text-zinc-500">{{ __('Nenhuma descrição registrada.') }}</flux:text>
        @endif

        @can('addObservation', $incident)
            <div class="space-y-3 border-t border-zinc-200 pt-4 dark:border-zinc-700">
                <flux:text size="sm" class="text-zinc-600 dark:text-zinc-400">
                    {{ __('O texto anterior é mantido. Cada anotação registra data, hora e operador.') }}
                </flux:text>

                @error('descriptionAppend')
                    <flux:callout variant="danger" class="text-sm">{{ $message }}</flux:callout>
                @enderror

                <flux:textarea
                    wire:model="descriptionAppend"
                    :label="__('Nova anotação')"
                    rows="3"
                    placeholder="{{ __('Descreva a nova informação…') }}"
                />

                <div>
                    <flux:button variant="primary" size="sm" wire:click="appendDescription" wire:loading.attr="disabled">
                        {{ __('Salvar na descrição') }}
                    </flux:button>
                </div>
            </div>
        @endcan
    </flux:card>

    @include('livewire.operations.partials.incident-call-alert-summary', ['callAlertReport' => $callAlertReport ?? null])

    <flux:card class="space-y-4">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <flux:subheading>{{ __('Vítimas') }} ({{ $incident->victims->count() }})</flux:subheading>
            @can('recordVictim', $incident)
                <flux:button size="sm" variant="primary" icon="user-plus" :href="route('operations.incidents.victims.create', $incident)" wire:navigate>
                    {{ __('Registrar vítima') }}
                </flux:button>
            @endcan
        </div>
        @if ($incident->victims->isEmpty())
            <flux:text size="sm" class="text-zinc-500">{{ __('Nenhuma vítima registrada.') }}</flux:text>
        @else
            <ul class="divide-y divide-zinc-200 dark:divide-zinc-700">
                @foreach ($incident->victims as $v)
                    <li wire:key="vic-{{ $v->id }}" class="flex flex-wrap items-center justify-between gap-2 py-3">
                        <div>
                            <flux:text class="font-medium">{{ $v->name ?: __('Sem nome') }}</flux:text>
                            <flux:text size="sm" class="text-zinc-500">
                                {{ __('Situação') }}:
                                @if ((int) $v->situacao === 1)
                                    {{ __('Atendida') }}
                                @elseif ((int) $v->situacao === 3)
                                    {{ __('Recusa') }}
                                @else
                                    —
                                @endif
                                @if ($v->age)
                                    · {{ $v->age }} {{ __('anos') }}
                                @endif
                            </flux:text>
                        </div>
                        <div class="flex flex-wrap gap-2">
                            @can('create', [Prescription::class, $v])
                                <flux:button size="sm" variant="ghost" :href="route('operations.victims.prescriptions.create', $v)" wire:navigate>
                                    {{ __('Prescrever') }}
                                </flux:button>
                            @endcan
                            @can('update', $v)
                                <flux:button size="sm" variant="ghost" :href="route('operations.incidents.victims.edit', [$incident, $v])" wire:navigate>
                                    {{ __('Editar') }}
                                </flux:button>
                            @endcan
                        </div>
                        @if ($v->prescriptions->isNotEmpty())
                            <div class="basis-full ps-0 md:ps-4">
                                <ul class="mt-2 space-y-1">
                                    @foreach ($v->prescriptions as $prescription)
                                        <li wire:key="prescription-{{ $prescription->id }}" class="flex flex-wrap items-center gap-2 text-sm text-zinc-600 dark:text-zinc-400">
                                            <span>{{ __('Prescrição #:id', ['id' => $prescription->id]) }}</span>
                                            <flux:badge size="sm" color="{{ $prescription->status->value === 'approved' ? 'green' : 'amber' }}">{{ $prescription->status->label() }}</flux:badge>
                                            <a class="text-blue-600 hover:underline dark:text-blue-400" href="{{ route('operations.prescriptions.approval', $prescription) }}" wire:navigate>
                                                {{ __('ver validação') }}
                                            </a>
                                        </li>
                                    @endforeach
                                </ul>
                            </div>
                        @endif
                    </li>
                @endforeach
            </ul>
        @endif
    </flux:card>

    @php
        $reg = $incident->regulation;
    @endphp
    @if ($reg && $reg->decided_at)
        <flux:card class="border-s-4 border-s-indigo-500 dark:border-s-indigo-400 space-y-3">
            <div class="flex flex-wrap items-center justify-between gap-2">
                <flux:subheading>{{ __('Regulação médica') }}</flux:subheading>
                <div class="flex items-center gap-2">
                    @if ($reg->status)
                        <flux:badge :color="$reg->status->authorizesDispatch() ? 'green' : 'zinc'">
                            {{ $reg->status->label() }}
                        </flux:badge>
                    @endif
                    @can('viewRegulation', $incident)
                        <flux:button size="sm" variant="ghost" icon="document-arrow-down"
                            :href="route('operations.incidents.regulation.document', $incident)" target="_blank">
                            {{ __('Ficha PDF') }}
                        </flux:button>
                    @endcan
                </div>
            </div>
            <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                <div>
                    <flux:text class="text-xs uppercase text-zinc-500">{{ __('Médico regulador') }}</flux:text>
                    <flux:text class="font-medium">{{ $reg->regulator?->name ?? '—' }}</flux:text>
                </div>
                <div>
                    <flux:text class="text-xs uppercase text-zinc-500">{{ __('Prioridade') }}</flux:text>
                    <flux:text class="font-medium">
                        @if ($reg->priority)
                            <flux:badge size="sm" :color="$reg->priority->fluxColor()">{{ $reg->priority->label() }}</flux:badge>
                        @else — @endif
                    </flux:text>
                </div>
                @if ($reg->recommended_resource)
                    <div>
                        <flux:text class="text-xs uppercase text-zinc-500">{{ __('Recurso indicado') }}</flux:text>
                        <flux:text class="font-medium">{{ $reg->recommended_resource->label() }}</flux:text>
                    </div>
                @endif
                <div>
                    <flux:text class="text-xs uppercase text-zinc-500">{{ __('Regulada em') }}</flux:text>
                    <flux:text class="font-medium tabular-nums">{{ $reg->decided_at->format('d/m/Y H:i') }}</flux:text>
                </div>
                @if ($reg->response_time_seconds !== null)
                    <div>
                        <flux:text class="text-xs uppercase text-zinc-500">{{ __('Tempo-resposta') }}</flux:text>
                        <flux:text class="font-medium tabular-nums">{{ gmdate('H:i:s', $reg->response_time_seconds) }}</flux:text>
                    </div>
                @endif
            </div>
            @if ($reg->diagnostic_hypothesis)
                <div>
                    <flux:text class="text-xs uppercase text-zinc-500">{{ __('Hipótese diagnóstica') }}</flux:text>
                    <flux:text class="whitespace-pre-line">{{ $reg->diagnostic_hypothesis }}</flux:text>
                </div>
            @endif
            @if ($reg->guidance_notes)
                <div>
                    <flux:text class="text-xs uppercase text-zinc-500">{{ __('Orientações') }}</flux:text>
                    <flux:text class="whitespace-pre-line">{{ $reg->guidance_notes }}</flux:text>
                </div>
            @endif
            @if ($reg->transfer_target)
                <div>
                    <flux:text class="text-xs uppercase text-zinc-500">{{ __('Destino da transferência') }}</flux:text>
                    <flux:text class="font-medium">{{ $reg->transfer_target }}</flux:text>
                </div>
            @endif
            @if ($reg->refusal_reason)
                <div>
                    <flux:text class="text-xs uppercase text-zinc-500">{{ __('Motivo da recusa') }}</flux:text>
                    <flux:text class="whitespace-pre-line">{{ $reg->refusal_reason }}</flux:text>
                </div>
            @endif
        </flux:card>
    @endif

    @php
        $activeDispatches = $incident->dispatches->whereNull('deleted_at');
    @endphp

    @if ($activeDispatches->isNotEmpty())
        <flux:card class="border-s-4 border-s-blue-500 dark:border-s-blue-400">
            <flux:subheading>{{ __('Viaturas empenhadas') }} ({{ $activeDispatches->count() }})</flux:subheading>
            <ul class="mt-3 divide-y divide-zinc-200 dark:divide-zinc-700">
                @foreach ($activeDispatches as $dispatch)
                    <li wire:key="active-dispatch-{{ $dispatch->id }}" class="flex flex-wrap items-center justify-between gap-2 py-3 first:pt-0 last:pb-0">
                        <div>
                            <flux:text class="font-medium">
                                {{ $dispatch->shift?->vehicle?->prefix ?? __('Viatura') }}
                                · {{ $dispatch->shift?->vehicle?->plate ?? __('Sem placa') }}
                            </flux:text>
                            <flux:text size="sm" class="text-zinc-600 dark:text-zinc-400">
                                {{ __('Etapa') }}: {{ $dispatch->stage->label() }}
                            </flux:text>
                        </div>
                    </li>
                @endforeach
            </ul>
        </flux:card>
    @endif

    @if ($modality === null || $modality === \App\Domain\Operations\Enums\IncidentReportModality::Samu)
        @can('fillNurseReport', $incident)
        <flux:card class="border-s-4 border-s-teal-500 dark:border-s-teal-400">
            <flux:subheading>{{ __('Relatório de enfermagem') }}</flux:subheading>
            @if ($incident->nurseReport)
                <flux:text size="sm" class="mt-2 text-zinc-600 dark:text-zinc-400">
                    {{ __('Registrado por :nome em :data.', [
                        'nome' => $incident->nurseReport->filledBy?->name ?? '—',
                        'data' => $incident->nurseReport->submitted_at->format('d/m/Y H:i'),
                    ]) }}
                </flux:text>
                <div class="mt-4 flex flex-wrap gap-2">
                    <flux:button variant="ghost" size="sm" icon="document-text" :href="route('operations.incidents.nurse-report', $incident)" wire:navigate>
                        {{ __('Editar relatório') }}
                    </flux:button>
                </div>
            @else
                @if ($incident->status === IncidentStatus::PendingNurseReport)
                    <flux:callout variant="warning" class="mt-3">
                        {{ __('A unidade retornou à base. A ocorrência permanece pendente até o envio deste relatório.') }}
                    </flux:callout>
                @else
                    <flux:callout variant="warning" class="mt-3">
                        {{ __('Complete o relatório assistencial desta ocorrência.') }}
                    </flux:callout>
                @endif
                <div class="mt-4">
                    <flux:button variant="primary" size="sm" icon="document-plus" :href="route('operations.incidents.nurse-report', $incident)" wire:navigate>
                        {{ __('Preencher relatório') }}
                    </flux:button>
                </div>
            @endif
        </flux:card>
        @endcan
    @endif

    @can('fillFinalReport', $incident)
        <flux:card class="border-s-4 border-s-orange-500 dark:border-s-orange-400">
            <flux:subheading>
                {{ __('Relatório final') }}
                @if ($modality)
                    <flux:badge size="sm" color="orange" class="ms-2">{{ $modality->label() }}</flux:badge>
                @endif
            </flux:subheading>
            @if ($incident->finalReport)
                <flux:text size="sm" class="mt-2 text-zinc-600 dark:text-zinc-400">
                    {{ __('Registrado por :nome em :data.', [
                        'nome' => $incident->finalReport->filledBy?->name ?? '—',
                        'data' => $incident->finalReport->submitted_at?->format('d/m/Y H:i') ?? '—',
                    ]) }}
                </flux:text>
                <div class="mt-4 flex flex-wrap gap-2">
                    <flux:button variant="ghost" size="sm" icon="document-text" :href="route('operations.incidents.final-report', $incident)" wire:navigate>
                        {{ __('Editar relatório final') }}
                    </flux:button>
                    @can('viewFinalReportDocument', $incident)
                        <flux:button variant="ghost" size="sm" icon="printer" :href="route('operations.incidents.final-report.document', $incident)" target="_blank">
                            {{ __('Imprimir relatório') }}
                        </flux:button>
                        <flux:button variant="ghost" size="sm" icon="arrow-down-tray" :href="route('operations.incidents.final-report.document', ['incident' => $incident, 'download' => 1])">
                            {{ __('Baixar PDF') }}
                        </flux:button>
                    @endcan
                </div>
            @else
                @if ($incident->status === IncidentStatus::PendingFinalReport)
                    <flux:callout variant="warning" class="mt-3">
                        {{ __('A viatura foi liberada. A ocorrência permanece pendente até o preenchimento do relatório final.') }}
                    </flux:callout>
                @else
                    <flux:callout variant="warning" class="mt-3">
                        {{ __('Preencha o relatório final desta ocorrência.') }}
                    </flux:callout>
                @endif
                <div class="mt-4">
                    <flux:button variant="primary" size="sm" icon="document-plus" :href="route('operations.incidents.final-report', $incident)" wire:navigate>
                        {{ __('Preencher relatório final') }}
                    </flux:button>
                </div>
            @endif
        </flux:card>
    @endcan

    <flux:card>
        <flux:subheading class="mb-4">{{ __('Marcos horários operacionais') }}</flux:subheading>
        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
            @foreach ([
                ['label' => __('Empenho'), 'value' => $incident->dispatched_at],
                ['label' => __('Saída da base (QTI)'), 'value' => $incident->departed_base_at],
                ['label' => __('Chegada ao local'), 'value' => $incident->arrived_scene_at],
                ['label' => __('Saída do local'), 'value' => $incident->left_scene_at],
                ['label' => __('Chegada na US'), 'value' => $incident->arrived_hospital_at],
                ['label' => __('Saída da US'), 'value' => $incident->released_hospital_at],
                ['label' => __('Retorno à base'), 'value' => $incident->returned_base_at],
            ] as $row)
                <div class="rounded-lg border border-zinc-200 px-3 py-2 dark:border-zinc-700">
                    <flux:text size="sm" class="text-zinc-500">{{ $row['label'] }}</flux:text>
                    <flux:text class="tabular-nums">{{ $row['value']?->format('d/m H:i:s') ?? '—' }}</flux:text>
                </div>
            @endforeach
        </div>
    </flux:card>

    <div class="grid gap-4 xl:grid-cols-2">
        @php
            $routeVehicle = $routeContext->vehicle;
            $hasDevice = $routeContext->hasDevice();
        @endphp
        <flux:card class="flex min-h-[22rem] flex-col" data-test="incident-route-map">
            <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
                <div>
                    <flux:subheading>{{ __('Percurso da viatura') }}</flux:subheading>
                    @if ($routeContext->from && $routeContext->to)
                        <flux:text size="sm" class="text-zinc-500">
                            {{ __('Intervalo do percurso') }}:
                            {{ $routeContext->from->format('d/m H:i') }}
                            —
                            {{ $routeContext->to->format('d/m H:i') }}
                        </flux:text>
                    @elseif ($routeContext->from)
                        <flux:text size="sm" class="text-zinc-500">
                            {{ __('Empenho') }}: {{ $routeContext->from->format('d/m H:i') }}
                        </flux:text>
                    @endif
                    @if ($routeContext->isHistoricalReplay())
                        <flux:text size="sm" class="text-zinc-500">
                            {{ __('Replay do trajeto registrado no Traccar durante o atendimento.') }}
                        </flux:text>
                    @endif
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    <div class="flex flex-wrap items-center gap-2 text-xs text-zinc-500">
                        @if ($hasDevice)
                            <span class="inline-flex items-center gap-1">
                                <span class="inline-block h-2 w-2 rounded-full bg-blue-500"></span>
                                {{ $routeVehicle->prefix ?? '' }} · Device {{ $routeVehicle->device_id }}
                            </span>
                        @elseif ($routeVehicle)
                            <span class="text-amber-600">{{ __('Viatura sem device Traccar') }}</span>
                        @elseif ($routeContext->dispatch)
                            <span class="text-amber-600">{{ __('Viatura do atendimento sem device Traccar') }}</span>
                        @else
                            <span>{{ __('Sem registro de viatura nesta ocorrência') }}</span>
                        @endif
                    </div>
                    @if ($routeContext->canFetchRoute())
                        <flux:button
                            :href="$this->routeMapFullscreenUrl()"
                            icon="arrow-top-right-on-square"
                            size="xs"
                            variant="ghost"
                            target="_blank"
                            data-test="incident-route-map-fullscreen-link"
                        >
                            {{ __('Abrir percurso em nova guia') }}
                        </flux:button>
                    @endif
                </div>
            </div>

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
                class="relative flex flex-1 flex-col"
            >
                {{-- Mapa --}}
                <div
                    x-ref="routeMapEl"
                    class="min-h-[18rem] flex-1 rounded-xl border border-zinc-200 bg-zinc-100 dark:border-zinc-700 dark:bg-zinc-800"
                    style="z-index:0"
                ></div>

                {{-- Legenda de estado sobre o mapa --}}
                <div
                    x-show="state === 'loading'"
                    class="pointer-events-none absolute inset-0 flex items-center justify-center rounded-xl bg-white/70 dark:bg-zinc-900/70"
                >
                    <flux:text class="text-zinc-500">{{ __('Carregando percurso registrado…') }}</flux:text>
                </div>

                <div
                    x-show="state === 'empty'"
                    class="pointer-events-none absolute inset-0 flex flex-col items-center justify-center rounded-xl bg-white/80 dark:bg-zinc-900/80"
                >
                    <flux:icon.map-pin class="size-8 text-zinc-400" />
                    <flux:text class="mt-2 text-zinc-500">{{ __('Sem pontos de rota no intervalo da ocorrência.') }}</flux:text>
                </div>

                <div
                    x-show="state === 'no_device'"
                    class="pointer-events-none absolute inset-0 flex flex-col items-center justify-center rounded-xl bg-white/80 dark:bg-zinc-900/80"
                >
                    <flux:icon.signal-slash class="size-8 text-zinc-400" />
                    <flux:text class="mt-2 text-zinc-500">{{ __('Viatura sem device Traccar vinculado.') }}</flux:text>
                </div>

                <div
                    x-show="state === 'error'"
                    class="pointer-events-none absolute inset-0 flex flex-col items-center justify-center rounded-xl bg-white/80 dark:bg-zinc-900/80"
                >
                    <flux:icon.exclamation-triangle class="size-8 text-amber-400" />
                    <flux:text class="mt-2 text-center text-zinc-500" x-text="errorMessage || @js(__('Não foi possível carregar a rota.'))"></flux:text>
                </div>

                <div class="mt-2 flex flex-wrap items-center justify-between gap-2">
                    <div x-show="state === 'loaded'" class="text-xs text-zinc-500" x-cloak>
                        <span x-text="pointCount === 1 ? @js(__('1 ponto GPS')) : @js(__(':count pontos GPS')).replace(':count', String(pointCount))"></span>
                    </div>
                    <flux:button
                        x-show="hasDevice"
                        type="button"
                        size="xs"
                        variant="ghost"
                        icon="arrow-path"
                        x-on:click="reloadRoute()"
                        x-bind:disabled="state === 'loading'"
                    >
                        {{ __('Recarregar percurso') }}
                    </flux:button>
                </div>

                {{-- Legenda visual --}}
                <div x-show="state === 'loaded'" class="mt-1 flex flex-wrap gap-3 text-xs text-zinc-500">
                    <span class="inline-flex items-center gap-1"><span class="inline-block h-2.5 w-2.5 rounded-full bg-red-500"></span>{{ __('Local') }}</span>
                    <span class="inline-flex items-center gap-1"><span class="inline-block h-2.5 w-2.5 rounded-full bg-green-500"></span>{{ __('Saída da base') }}</span>
                    <span class="inline-flex items-center gap-1"><span class="inline-block h-[3px] w-5 rounded bg-blue-500"></span>{{ __('Percurso') }}</span>
                    <span class="inline-flex items-center gap-1"><span class="inline-block h-2.5 w-2.5 rounded-full bg-amber-400"></span>{{ __('Retorno') }}</span>
                </div>
            </div>
        </flux:card>

        <flux:card>
            <flux:subheading class="mb-4">{{ __('Timeline auditável') }}</flux:subheading>
            @if ($incident->timelineEvents->isEmpty())
                <flux:text size="sm">{{ __('Sem eventos.') }}</flux:text>
            @else
                @php
                    // Classes literais por tom (mantidas no Blade para não serem purgadas pelo Tailwind).
                    $tlTones = [
                        'blue' => ['card' => 'border-blue-200 bg-blue-50/50 dark:border-blue-500/30 dark:bg-blue-950/20', 'icon' => 'bg-blue-100 text-blue-600 dark:bg-blue-500/20 dark:text-blue-300', 'title' => 'text-blue-800 dark:text-blue-200'],
                        'emerald' => ['card' => 'border-emerald-200 bg-emerald-50/50 dark:border-emerald-500/30 dark:bg-emerald-950/20', 'icon' => 'bg-emerald-100 text-emerald-600 dark:bg-emerald-500/20 dark:text-emerald-300', 'title' => 'text-emerald-800 dark:text-emerald-200'],
                        'amber' => ['card' => 'border-amber-200 bg-amber-50/50 dark:border-amber-500/30 dark:bg-amber-950/20', 'icon' => 'bg-amber-100 text-amber-600 dark:bg-amber-500/20 dark:text-amber-300', 'title' => 'text-amber-800 dark:text-amber-200'],
                        'indigo' => ['card' => 'border-indigo-200 bg-indigo-50/50 dark:border-indigo-500/30 dark:bg-indigo-950/20', 'icon' => 'bg-indigo-100 text-indigo-600 dark:bg-indigo-500/20 dark:text-indigo-300', 'title' => 'text-indigo-800 dark:text-indigo-200'],
                        'rose' => ['card' => 'border-rose-200 bg-rose-50/50 dark:border-rose-500/30 dark:bg-rose-950/20', 'icon' => 'bg-rose-100 text-rose-600 dark:bg-rose-500/20 dark:text-rose-300', 'title' => 'text-rose-800 dark:text-rose-200'],
                        'teal' => ['card' => 'border-teal-200 bg-teal-50/50 dark:border-teal-500/30 dark:bg-teal-950/20', 'icon' => 'bg-teal-100 text-teal-600 dark:bg-teal-500/20 dark:text-teal-300', 'title' => 'text-teal-800 dark:text-teal-200'],
                        'violet' => ['card' => 'border-violet-200 bg-violet-50/50 dark:border-violet-500/30 dark:bg-violet-950/20', 'icon' => 'bg-violet-100 text-violet-600 dark:bg-violet-500/20 dark:text-violet-300', 'title' => 'text-violet-800 dark:text-violet-200'],
                        'zinc' => ['card' => 'border-zinc-200 bg-zinc-50/70 dark:border-zinc-700 dark:bg-zinc-800/30', 'icon' => 'bg-zinc-200 text-zinc-600 dark:bg-zinc-700 dark:text-zinc-300', 'title' => 'text-zinc-800 dark:text-zinc-200'],
                    ];
                @endphp
                <ul class="max-h-[32rem] space-y-3 overflow-y-auto pe-1">
                    @foreach ($incident->timelineEvents as $event)
                        @php
                            $style = \App\Support\Operations\TimelineEventStyle::for($event->event_key);
                            $tone = $tlTones[$style['tone']] ?? $tlTones['zinc'];
                        @endphp
                        <li wire:key="det-tl-{{ $event->id }}" class="rounded-xl border p-3 {{ $tone['card'] }}">
                            <div class="flex items-start gap-3">
                                <span class="mt-0.5 flex size-8 shrink-0 items-center justify-center rounded-lg {{ $tone['icon'] }}">
                                    <flux:icon :icon="$style['icon']" variant="mini" class="size-4" />
                                </span>
                                <div class="min-w-0 flex-1">
                                    <div class="flex flex-wrap items-baseline justify-between gap-x-3 gap-y-0.5">
                                        <flux:text class="font-semibold {{ $tone['title'] }}">{{ TimelineEventLabels::for($event->event_key) }}</flux:text>
                                        <flux:text size="sm" class="tabular-nums text-zinc-500">{{ $event->recorded_at->format('d/m/Y H:i:s') }}</flux:text>
                                    </div>

                                    @if ($event->event_key === 'incident_description_appended' && is_array($event->payload) && ! empty($event->payload['text']))
                                        <flux:text size="sm" class="mt-2 whitespace-pre-wrap text-zinc-600 dark:text-zinc-400">{{ $event->payload['text'] }}</flux:text>
                                    @else
                                        @php $tlRows = TimelineEventPayloadFormatter::rows($event); @endphp
                                        @if ($tlRows !== [])
                                            <dl class="mt-2 grid grid-cols-1 gap-x-4 gap-y-1.5 sm:grid-cols-2">
                                                @foreach ($tlRows as $row)
                                                    <div class="flex flex-col">
                                                        <dt class="text-[0.65rem] font-semibold uppercase tracking-wide text-zinc-400 dark:text-zinc-500">{{ $row['label'] }}</dt>
                                                        <dd class="text-sm text-zinc-700 dark:text-zinc-300">
                                                            {{ $row['value'] }}
                                                            @if (! empty($row['hint']))
                                                                <span class="block text-[0.7rem] font-normal leading-tight text-zinc-400 dark:text-zinc-500">{{ $row['hint'] }}</span>
                                                            @endif
                                                        </dd>
                                                    </div>
                                                @endforeach
                                            </dl>
                                        @endif
                                    @endif

                                    @if ($event->actor)
                                        <flux:text size="xs" class="mt-2 flex items-center gap-1 text-zinc-500">
                                            <flux:icon icon="user-circle" variant="mini" class="size-3.5" />
                                            {{ $event->actor->name }}
                                        </flux:text>
                                    @endif
                                </div>
                            </div>
                        </li>
                    @endforeach
                </ul>
            @endif
        </flux:card>
    </div>
</div>
