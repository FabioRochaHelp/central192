<div class="cco-page-gap">
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <flux:heading size="xl">{{ __('Identificar chamada') }}</flux:heading>
            <flux:text class="mt-1">{{ __('Informe o telefone de origem. Em seguida você preenche o cadastro da ocorrência (equivalente ao fluxo manual legado).') }}</flux:text>
        </div>
        <div class="flex flex-wrap gap-2">
            <flux:button variant="ghost" icon="rectangle-stack" :href="route('operations.incidents.index')" wire:navigate>{{ __('Lista') }}</flux:button>
            <flux:button variant="ghost" icon="radio" :href="route('operations.dispatch')" wire:navigate>{{ __('CCO') }}</flux:button>
        </div>
    </div>

    @if ($errors->any())
        <flux:callout variant="danger">
            <ul class="mt-1 list-inside list-disc space-y-1 text-sm">
                @foreach ($errors->all() as $message)
                    <li>{{ $message }}</li>
                @endforeach
            </ul>
        </flux:callout>
    @endif

    <flux:card>
        <form wire:submit="continueToForm" class="grid max-w-lg gap-4">
            <flux:input
                wire:model="caller_phone"
                type="tel"
                autocomplete="tel"
                :label="__('Telefone da chamada')"
                :placeholder="__('DDD + número')"
                description="{{ __('O número será normalizado (somente dígitos) ao continuar.') }}"
            />

            <flux:button type="submit" variant="primary" wire:loading.attr="disabled">{{ __('Continuar para o cadastro') }}</flux:button>
        </form>
    </flux:card>
</div>
