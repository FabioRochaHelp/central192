@php use Illuminate\Support\Facades\Gate; @endphp

<div class="mx-auto flex w-full max-w-4xl flex-col gap-4">
    <div class="grid gap-4 md:grid-cols-2">
        <flux:card class="space-y-4">
            <flux:subheading>{{ __('Ingestão') }}</flux:subheading>
            @if (Gate::check('createOperational'))
                <flux:text size="sm">{{ __('Registro rápido para exercício do fluxo (usa a primeira natureza cadastrada).') }}</flux:text>
                <flux:button variant="primary" icon="plus" wire:click="createDemoIncident">{{ __('Nova ocorrência (demo)') }}</flux:button>
            @else
                <flux:callout variant="warning">{{ __('Sem permissão para registrar ocorrência.') }}</flux:callout>
            @endif
        </flux:card>

        <flux:card class="space-y-4">
            <flux:subheading>{{ __('Empenho') }}</flux:subheading>
            <flux:text size="sm" class="text-slate-600 dark:text-slate-400">
                {{ __('Clique numa linha da fila de ocorrências abaixo para abrir o modal e escolher o turno (viatura) que vai atender.') }}
            </flux:text>
            <flux:callout variant="info">{{ __('A coluna à esquerda é só referência de turnos. No cadastro da ocorrência não há vínculo com base nem com turno; a base aparece ao empenhar, ao escolher a viatura no modal.') }}</flux:callout>
        </flux:card>
    </div>

    <div class="flex flex-wrap gap-2">
        <flux:button size="sm" variant="ghost" icon="rectangle-stack" :href="route('operations.incidents.index')" wire:navigate>
            {{ __('Lista de ocorrências') }}
        </flux:button>
        <flux:button size="sm" variant="ghost" icon="truck" :href="route('operations.fleet')" wire:navigate>
            {{ __('Turnos e viaturas') }}
        </flux:button>
        @if (auth()->user()?->isOperationalCentral())
            <flux:button size="sm" variant="ghost" icon="adjustments-horizontal" :href="route('operations.parameters.accessories')" wire:navigate>
                {{ __('Parâmetros da ocorrência') }}
            </flux:button>
            <flux:button size="sm" variant="ghost" icon="building-office-2" :href="route('operations.cadastro.bases')" wire:navigate>
                {{ __('Cadastro — bases') }}
            </flux:button>
        @endif
        <flux:button size="sm" variant="ghost" icon="cube" :href="route('operations.cadastro.vehicles')" wire:navigate>
            {{ __('Cadastro — viaturas') }}
        </flux:button>
        <flux:button size="sm" variant="ghost" icon="users" :href="route('operations.cadastro.staff')" wire:navigate>
            {{ __('Cadastro — efetivo') }}
        </flux:button>
        @if (Gate::check('createOperational'))
            <flux:button size="sm" variant="ghost" icon="plus-circle" :href="route('operations.incidents.start')" wire:navigate>
                {{ __('Cadastro — ocorrência') }}
            </flux:button>
        @endif
    </div>
</div>
