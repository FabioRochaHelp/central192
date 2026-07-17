<div class="cco-page-gap">
    <div class="flex flex-wrap items-center gap-3">
        <flux:button variant="ghost" icon="arrow-left" :href="route('operations.incidents.show', $prescription->victim->incident)" wire:navigate>
            {{ __('Voltar à ocorrência') }}
        </flux:button>
    </div>

    <div class="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
        <div>
            <flux:heading size="xl">{{ __('Validação de prescrição') }}</flux:heading>
            <flux:text class="mt-1 text-zinc-600 dark:text-zinc-400">
                {{ __('Vítima: :name · Ocorrência #:talao/:ano', [
                    'name' => $prescription->victim->name ?: __('Sem nome'),
                    'talao' => $prescription->victim->incident->talao,
                    'ano' => $prescription->victim->incident->dispatch_year,
                ]) }}
            </flux:text>
        </div>
        <flux:badge color="{{ $prescription->status->value === 'approved' ? 'green' : 'amber' }}" size="lg">
            {{ $prescription->status->label() }}
        </flux:badge>
    </div>

    @error('approve')
        <flux:callout variant="danger">{{ $message }}</flux:callout>
    @enderror

    <div class="grid gap-4 lg:grid-cols-3">
        <flux:card class="space-y-2">
            <flux:subheading>{{ __('Vítima') }}</flux:subheading>
            <flux:text>{{ $prescription->victim->name ?: '—' }}</flux:text>
            <flux:text size="sm" class="text-zinc-500">{{ __('RG') }}: {{ $prescription->victim->rg ?: '—' }}</flux:text>
        </flux:card>
        <flux:card class="space-y-2 lg:col-span-2">
            <flux:subheading>{{ __('Ocorrência') }}</flux:subheading>
            <flux:text class="tabular-nums">#{{ $prescription->victim->incident->talao }}/{{ $prescription->victim->incident->dispatch_year }}</flux:text>
            <flux:text size="sm" class="text-zinc-500">
                {{ trim(implode(', ', array_filter([$prescription->victim->incident->address_line, $prescription->victim->incident->number, $prescription->victim->incident->district, $prescription->victim->incident->city]))) ?: '—' }}
            </flux:text>
        </flux:card>
    </div>

    <div class="grid gap-4 lg:grid-cols-3">
        <flux:card class="space-y-2">
            <flux:subheading>{{ __('Prescritor') }}</flux:subheading>
            <flux:text>{{ $prescription->prescribedBy?->name ?? '—' }}</flux:text>
            <flux:text size="sm" class="text-zinc-500">{{ $prescription->created_at->format('d/m/Y H:i') }}</flux:text>
        </flux:card>

        <flux:card class="space-y-2">
            <flux:subheading>{{ __('Médico do atendimento') }}</flux:subheading>
            <flux:text>{{ $prescription->medicalStaff?->name ?? '—' }}</flux:text>
        </flux:card>

        <flux:card class="space-y-2">
            <flux:subheading>{{ __('Aprovação') }}</flux:subheading>
            <flux:text>{{ $prescription->approvedBy?->name ?? '—' }}</flux:text>
            <flux:text size="sm" class="text-zinc-500">{{ $prescription->approved_at?->format('d/m/Y H:i') ?? '—' }}</flux:text>
        </flux:card>
    </div>

    @if ($prescription->description)
        <flux:card>
            <flux:subheading>{{ __('Descrição / orientação médica') }}</flux:subheading>
            <flux:text class="mt-2 whitespace-pre-line">{{ $prescription->description }}</flux:text>
        </flux:card>
    @endif

    <flux:card class="space-y-4">
        <flux:subheading>{{ __('Itens prescritos') }}</flux:subheading>
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead class="text-zinc-500">
                    <tr>
                        <th class="py-2 pe-4">{{ __('Medicamento') }}</th>
                        <th class="py-2">{{ __('Quantidade') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700">
                    @foreach ($prescription->items as $item)
                        <tr wire:key="prescription-approval-item-{{ $item->id }}">
                            <td class="py-3 pe-4">{{ $item->medication_name }}</td>
                            <td class="py-3 tabular-nums">{{ $item->quantity }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </flux:card>

    @can('approve', $prescription)
        <div class="flex flex-wrap gap-2">
            <flux:button type="button" variant="primary" wire:click="approve" wire:loading.attr="disabled">
                {{ __('Aprovar prescrição') }}
            </flux:button>
        </div>
    @endcan
</div>
