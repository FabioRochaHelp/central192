@php
    use App\Domain\Operations\Enums\IncidentReportModality;
    $modality = $incident->nature?->report_modality;
@endphp

<div class="cco-page-gap">
    <div class="flex flex-wrap items-center gap-3">
        <flux:button variant="ghost" icon="arrow-left" :href="route('operations.incidents.show', $incident)" wire:navigate>
            {{ __('Voltar à ocorrência') }}
        </flux:button>
    </div>

    <div class="flex flex-col gap-2">
        <flux:heading size="xl">{{ __('Relatório final') }}</flux:heading>
        <flux:text class="text-zinc-600 dark:text-zinc-400">
            {{ __('Talão :talao/:ano', ['talao' => $incident->talao, 'ano' => $incident->dispatch_year]) }}
            @if ($modality)
                · <span class="font-medium">{{ $modality->label() }}</span>
            @endif
        </flux:text>
        <flux:text size="sm" class="text-zinc-500 dark:text-zinc-400">
            {{ __('Preencha o relatório para encerrar a ocorrência.') }}
        </flux:text>
        @if ($incident->finalReport)
            @can('viewFinalReportDocument', $incident)
                <div class="flex flex-wrap gap-2 pt-1">
                    <flux:button variant="ghost" size="sm" icon="printer" :href="route('operations.incidents.final-report.document', $incident)" target="_blank">
                        {{ __('Imprimir relatório') }}
                    </flux:button>
                    <flux:button variant="ghost" size="sm" icon="arrow-down-tray" :href="route('operations.incidents.final-report.document', ['incident' => $incident, 'download' => 1])">
                        {{ __('Baixar PDF') }}
                    </flux:button>
                </div>
            @endcan
        @endif
    </div>

    @if ($incident->pabx_uniqueid && auth()->user()?->isCentralAdministrator())
        {{-- Áudio servido pelo proxy do Laravel (mesma origem, sessão autenticada).
             O Bearer token do PABX fica só no servidor e nunca chega ao navegador. --}}
        <flux:card class="space-y-2" x-data="{ error: false }">
            <flux:subheading>{{ __('Gravação da chamada') }}</flux:subheading>
            <audio
                controls
                preload="none"
                class="w-full"
                src="{{ route('operations.incidents.recording', $incident) }}"
                x-on:error="error = true"
                x-on:loadeddata="error = false"
            >
                {{ __('Seu navegador não suporta reprodução de áudio.') }}
            </audio>
            <flux:text size="sm" class="text-red-600 dark:text-red-400" x-show="error" x-cloak>
                {{ __('Não foi possível carregar a gravação no servidor.') }}
            </flux:text>
        </flux:card>
    @endif

    @error('save')
        <flux:callout variant="danger">{{ $message }}</flux:callout>
    @enderror

    @if (! $modality || ! $modality->usesFinalReport())
        <flux:callout variant="warning">
            {{ __('Esta ocorrência não possui modalidade de relatório final definida na natureza.') }}
        </flux:callout>
    @else
        <form wire:submit="save" class="grid gap-6">
            {{-- Campos base --}}
            <flux:card class="space-y-6">
                <flux:subheading>{{ __('Dados gerais') }}</flux:subheading>

                <div class="grid gap-4 sm:grid-cols-3">
                    <flux:field>
                        <flux:label>{{ __('Resgatados com vida') }}</flux:label>
                        <flux:input type="number" wire:model="victims_rescued" min="0" />
                        <flux:error name="victims_rescued" />
                    </flux:field>
                    <flux:field>
                        <flux:label>{{ __('Feridos') }}</flux:label>
                        <flux:input type="number" wire:model="victims_injured" min="0" />
                        <flux:error name="victims_injured" />
                    </flux:field>
                    <flux:field>
                        <flux:label>{{ __('Óbitos confirmados') }}</flux:label>
                        <flux:input type="number" wire:model="victims_deceased" min="0" />
                        <flux:error name="victims_deceased" />
                    </flux:field>
                </div>

                <flux:textarea
                    wire:model="resources_summary"
                    :label="__('Resumo de recursos empregados')"
                    rows="3"
                    placeholder="{{ __('Viaturas, pessoal, equipamentos utilizados…') }}"
                />

                <flux:textarea
                    wire:model="external_support"
                    :label="__('Apoios externos acionados')"
                    rows="3"
                    placeholder="{{ __('Outras agências, suporte especializado…') }}"
                />

                <flux:textarea
                    wire:model="observations"
                    :label="__('Observações gerais')"
                    rows="4"
                />
            </flux:card>

            {{-- Sub-formulário por modalidade --}}
            @if ($modality === IncidentReportModality::FireForest)
                @include('livewire.operations.partials.final-report-fire-forest')
            @elseif ($modality === IncidentReportModality::FireBuilding)
                @include('livewire.operations.partials.final-report-fire-building')
            @elseif ($modality === IncidentReportModality::RescueAnimal)
                @include('livewire.operations.partials.final-report-rescue-animal')
            @elseif ($modality === IncidentReportModality::RescueInsects)
                @include('livewire.operations.partials.final-report-rescue-insect')
            @elseif ($modality === IncidentReportModality::RescueOther)
                @include('livewire.operations.partials.final-report-rescue-other')
            @endif

            <div class="flex flex-wrap gap-2">
                <flux:button type="submit" variant="primary" wire:loading.attr="disabled">
                    {{ __('Salvar relatório') }}
                </flux:button>
                <flux:button variant="ghost" type="button" :href="route('operations.incidents.show', $incident)" wire:navigate>
                    {{ __('Cancelar') }}
                </flux:button>
            </div>
        </form>
    @endif
</div>
