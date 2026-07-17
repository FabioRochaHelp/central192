{{-- Tempo real via Reverb (operations.dispatch). wire:poll longo = fallback se o WebSocket cair. --}}
<div class="cco-page-gap" wire:poll.120s>
    @include('livewire.operations.partials.dispatch-header')
    @include('livewire.operations.partials.dispatch-alerts')

    <section class="cco-surface">
        <div class="cco-surface-header">
            <div>
                <div class="cco-surface-title">
                    <flux:icon.radio class="size-4 text-blue-700 dark:text-blue-300" />
                    <span>{{ __('Despacho') }}</span>
                </div>
                <div class="cco-surface-subtitle">{{ __('Fila, turnos disponíveis e viaturas sem turno.') }}</div>
            </div>
            <flux:badge color="blue" size="sm">{{ __('Atualiza a cada 10s') }}</flux:badge>
        </div>

        <div class="grid gap-4 lg:grid-cols-12 lg:items-start">
            <aside class="order-1 lg:col-span-2 xl:col-span-2">
                @include('livewire.operations.partials.dispatch-column-shifts')
            </aside>

            <section class="order-2 flex min-w-0 flex-col gap-4 lg:col-span-8 xl:col-span-8">
                @include('livewire.operations.partials.open-incidents')
                @include('livewire.operations.partials.dispatch-dispatch-modal')
                @include('livewire.operations.partials.dispatch-closure-modal')
                @include('livewire.operations.partials.dispatch-incident-actions-modal')
                @include('livewire.operations.partials.dispatch-shift-checklist-modal')
            </section>

            <aside class="order-3 lg:col-span-2 xl:col-span-2">
                @include('livewire.operations.partials.dispatch-column-idle-vehicles')
            </aside>
        </div>
    </section>

    <section class="cco-surface cco-surface--kanban">
        <div class="cco-surface-header">
            <div>
                <div class="cco-surface-title">
                    <flux:icon.rectangle-stack class="size-4 text-blue-700 dark:text-blue-300" />
                    <span>{{ __('Kanban operacional') }}</span>
                </div>
                <div class="cco-surface-subtitle">{{ __('Etapas do empenho até retorno.') }}</div>
            </div>
        </div>

        @include('livewire.operations.partials.kanban')
        @include('livewire.operations.partials.dispatch-kanban-modal')
    </section>

    <section class="cco-surface">
        <div class="cco-surface-header">
            <div>
                <div class="cco-surface-title">
                    <flux:icon.chart-bar class="size-4 text-blue-700 dark:text-blue-300" />
                    <span>{{ __('Indicadores') }}</span>
                </div>
                <div class="cco-surface-subtitle">{{ __('Resumo rápido do estado operacional.') }}</div>
            </div>
        </div>

        @include('livewire.operations.partials.tactical-strip', ['stats' => $stats])
    </section>

    <section class="cco-surface">
        <div class="cco-surface-header">
            <div>
                <div class="cco-surface-title">
                    <flux:icon.map class="size-4 text-blue-700 dark:text-blue-300" />
                    <span>{{ __('Mapa e feed') }}</span>
                </div>
                <div class="cco-surface-subtitle">{{ __('Camadas táticas e últimos eventos.') }}</div>
            </div>
            <flux:button href="{{ route('operations.tactical-map') }}" color="orange" icon="arrow-top-right-on-square"
                size="sm" variant="primary" target="_blank">
                {{ __('Abrir mapa em nova guia') }}
            </flux:button>
        </div>

        @include('livewire.operations.partials.map-and-feed')
    </section>
</div>
