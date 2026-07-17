<div class="cco-page-gap" wire:poll.15s>
    <div class="flex flex-col gap-2">
        <flux:heading size="xl">{{ __('Regulação médica') }}</flux:heading>
        <flux:text class="text-zinc-600 dark:text-zinc-400">
            {{ __('Ocorrências aguardando avaliação do médico regulador. A viatura só é empenhada após a decisão de envio de recurso.') }}
        </flux:text>
    </div>

    <div class="flex flex-wrap gap-3">
        <flux:badge color="amber" size="lg">{{ __('Aguardando: :n', ['n' => $pendingCount]) }}</flux:badge>
        <flux:badge color="blue" size="lg">{{ __('Em regulação: :n', ['n' => $inRegulationCount]) }}</flux:badge>
    </div>

    @if ($boardMessage !== '')
        <flux:callout variant="success">{{ $boardMessage }}</flux:callout>
    @endif
    @error('board')
        <flux:callout variant="danger">{{ $message }}</flux:callout>
    @enderror

    @if ($incidents->isEmpty())
        <flux:card>
            <flux:text class="text-zinc-500">{{ __('Nenhuma ocorrência na fila de regulação.') }}</flux:text>
        </flux:card>
    @else
        <div class="grid gap-3">
            @foreach ($incidents as $incident)
                @php($regulation = $incident->regulation)
                <flux:card class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                    <div class="flex flex-col gap-1">
                        <div class="flex flex-wrap items-center gap-2">
                            <flux:heading size="lg">
                                {{ __('Talão :talao/:ano', ['talao' => $incident->talao, 'ano' => $incident->dispatch_year]) }}
                            </flux:heading>
                            @if ($incident->manchester_risk)
                                <flux:badge :color="$incident->manchester_risk->fluxColor()">
                                    {{ $incident->manchester_risk->label() }}
                                </flux:badge>
                            @endif
                            <flux:badge :color="$incident->status === \App\Domain\Operations\Enums\IncidentStatus::InRegulation ? 'blue' : 'amber'">
                                {{ $incident->status->label() }}
                            </flux:badge>
                        </div>
                        <flux:text class="font-medium">{{ $incident->nature?->name ?? __('Sem natureza') }}</flux:text>
                        <flux:text class="text-sm text-zinc-600 dark:text-zinc-400">
                            {{ $incident->patient_name ?: __('Paciente não identificado') }}
                            @if ($incident->patient_age)· {{ $incident->patient_age }} {{ __('anos') }}@endif
                            @if ($incident->patient_sex)· {{ $incident->patient_sex }}@endif
                        </flux:text>
                        <flux:text class="text-sm text-zinc-500">
                            {{ $incident->address_line }}{{ $incident->district ? ', '.$incident->district : '' }}{{ $incident->city ? ' — '.$incident->city : '' }}
                        </flux:text>
                        <flux:text class="text-xs text-zinc-500">
                            {{ __('Ligação recebida :quando', ['quando' => ($incident->call_received_at ?? $incident->occurred_at)?->diffForHumans()]) }}
                            @if ($regulation?->regulator)
                                · {{ __('Assumida por :medico', ['medico' => $regulation->regulator->name]) }}
                            @endif
                        </flux:text>
                    </div>
                    <div class="flex shrink-0 gap-2">
                        <flux:button
                            variant="ghost"
                            icon="eye"
                            :href="route('operations.incidents.show', $incident)"
                            wire:navigate
                        >
                            {{ __('Detalhe') }}
                        </flux:button>
                        <flux:button
                            variant="primary"
                            icon="clipboard-document-check"
                            wire:click="assume({{ $incident->id }})"
                            wire:loading.attr="disabled"
                        >
                            {{ $incident->status === \App\Domain\Operations\Enums\IncidentStatus::InRegulation ? __('Continuar') : __('Assumir') }}
                        </flux:button>
                    </div>
                </flux:card>
            @endforeach
        </div>
    @endif
</div>
