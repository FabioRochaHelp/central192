<div class="cco-page-gap">
    <div class="flex flex-wrap items-center gap-3">
        <flux:button variant="ghost" icon="arrow-left" :href="route('operations.incidents.show', $victim->incident)" wire:navigate>
            {{ __('Voltar à ocorrência') }}
        </flux:button>
    </div>

    <div>
        <flux:heading size="xl">{{ __('Nova prescrição médica') }}</flux:heading>
        <flux:text class="mt-1 text-zinc-600 dark:text-zinc-400">
            {{ __('Vítima: :name · Ocorrência #:talao/:ano', [
                'name' => $victim->name ?: __('Sem nome'),
                'talao' => $victim->incident->talao,
                'ano' => $victim->incident->dispatch_year,
            ]) }}
        </flux:text>
    </div>

    @error('save')
        <flux:callout variant="danger">{{ $message }}</flux:callout>
    @enderror

    <div class="grid gap-4 lg:grid-cols-3">
        <flux:card class="space-y-2">
            <flux:subheading>{{ __('Vítima') }}</flux:subheading>
            <flux:text>{{ $victim->name ?: '—' }}</flux:text>
            <flux:text size="sm" class="text-zinc-500">{{ __('RG') }}: {{ $victim->rg ?: '—' }}</flux:text>
        </flux:card>
        <flux:card class="space-y-2 lg:col-span-2">
            <flux:subheading>{{ __('Ocorrência') }}</flux:subheading>
            <flux:text class="tabular-nums">#{{ $victim->incident->talao }}/{{ $victim->incident->dispatch_year }}</flux:text>
            <flux:text size="sm" class="text-zinc-500">
                {{ trim(implode(', ', array_filter([$victim->incident->address_line, $victim->incident->number, $victim->incident->district, $victim->incident->city]))) ?: '—' }}
            </flux:text>
        </flux:card>
    </div>

    @if ($victim->prescriptions->isNotEmpty())
        <flux:card class="space-y-3">
            <flux:subheading>{{ __('Prescrições já registradas') }}</flux:subheading>
            <ul class="divide-y divide-zinc-200 dark:divide-zinc-700">
                @foreach ($victim->prescriptions as $previous)
                    <li wire:key="previous-prescription-{{ $previous->id }}" class="flex flex-wrap items-center justify-between gap-2 py-2">
                        <div>
                            <flux:text>{{ __('Prescrição #:id', ['id' => $previous->id]) }}</flux:text>
                            <flux:text size="sm" class="text-zinc-500">{{ $previous->items->pluck('medication_name')->join(', ') ?: '—' }}</flux:text>
                        </div>
                        <div class="flex items-center gap-2">
                            <flux:badge size="sm" color="{{ $previous->status->value === 'approved' ? 'green' : 'amber' }}">{{ $previous->status->label() }}</flux:badge>
                            <flux:button size="sm" variant="ghost" :href="route('operations.prescriptions.approval', $previous)" wire:navigate>{{ __('Abrir') }}</flux:button>
                        </div>
                    </li>
                @endforeach
            </ul>
        </flux:card>
    @endif

    <form wire:submit="save" class="grid gap-6">
        <flux:card class="grid gap-4 md:grid-cols-2">
            <flux:subheading class="md:col-span-2">{{ __('Dados da prescrição') }}</flux:subheading>

            <flux:select wire:model="medical_staff_id" :label="__('Médico do atendimento')" placeholder="{{ __('Opcional') }}">
                <flux:select.option value="">{{ __('—') }}</flux:select.option>
                @foreach ($medicalStaff as $staff)
                    <flux:select.option value="{{ $staff->id }}">{{ $staff->name }}</flux:select.option>
                @endforeach
            </flux:select>

            <flux:textarea wire:model="description" :label="__('Descrição / orientação médica')" rows="3" class="md:col-span-2" />
        </flux:card>

        <flux:card class="space-y-4">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <flux:subheading>{{ __('Medicamentos prescritos') }}</flux:subheading>
                <flux:button type="button" size="sm" variant="ghost" icon="plus" wire:click="addItem">{{ __('Adicionar item') }}</flux:button>
            </div>

            <flux:callout>
                {{ __('Estoque não será validado nem baixado nesta etapa; informe o medicamento e a quantidade prescrita.') }}
            </flux:callout>

            @foreach ($items as $idx => $item)
                <div wire:key="prescription-item-{{ $idx }}" class="grid gap-3 rounded-xl border border-zinc-200 p-4 dark:border-zinc-700 md:grid-cols-[1fr_10rem_auto]">
                    <flux:input wire:model="items.{{ $idx }}.medication_name" :label="__('Medicamento')" />
                    <flux:input wire:model="items.{{ $idx }}.quantity" type="number" min="1" :label="__('Quantidade')" />
                    <div class="flex items-end">
                        @if (count($items) > 1)
                            <flux:button type="button" variant="ghost" wire:click="removeItem({{ $idx }})">{{ __('Remover') }}</flux:button>
                        @endif
                    </div>
                </div>
            @endforeach
        </flux:card>

        <div class="flex flex-wrap gap-2">
            <flux:button type="submit" variant="primary" wire:loading.attr="disabled">{{ __('Criar prescrição') }}</flux:button>
            <flux:button variant="ghost" type="button" :href="route('operations.incidents.show', $victim->incident)" wire:navigate>{{ __('Cancelar') }}</flux:button>
        </div>
    </form>
</div>
