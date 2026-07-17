<div class="cco-page-gap">
    <div class="flex flex-wrap items-center gap-3">
        <flux:button variant="ghost" icon="arrow-left" :href="route('operations.incidents.show', $incident)" wire:navigate>
            {{ __('Voltar à ocorrência') }}
        </flux:button>
    </div>

    <div class="flex flex-col gap-2">
        <flux:heading size="xl">{{ __('Relatório de enfermagem') }}</flux:heading>
        <flux:text class="text-zinc-600 dark:text-zinc-400">
            {{ __('Talão :talao/:ano — após o retorno à base a ocorrência fica pendente até este relatório; ao salvar, ela é encerrada.', ['talao' => $incident->talao, 'ano' => $incident->dispatch_year]) }}
        </flux:text>
    </div>

    @error('save')
        <flux:callout variant="danger">{{ $message }}</flux:callout>
    @enderror

    <flux:card>
        <form wire:submit="save" class="grid gap-6">
            <flux:textarea
                wire:model="clinical_evolution"
                :label="__('Evolução / relatório assistencial')"
                required
                rows="10"
                placeholder="{{ __('Descreva a evolução clínica, intercorrências e encerramento assistencial na origem / transporte.') }}"
            />

            <flux:textarea
                wire:model="conduct_summary"
                :label="__('Conduta e intervenções')"
                rows="6"
                placeholder="{{ __('Procedimentos, medicações administradas no campo (se aplicável), orientações.') }}"
            />

            <flux:textarea
                wire:model="destination_notes"
                :label="__('Destino / unidade de saúde')"
                rows="4"
                placeholder="{{ __('Unidade receptora, setor, observações de encaminhamento.') }}"
            />

            <div class="flex flex-wrap gap-2">
                <flux:button type="submit" variant="primary" wire:loading.attr="disabled">{{ __('Salvar relatório') }}</flux:button>
                <flux:button variant="ghost" type="button" :href="route('operations.incidents.show', $incident)" wire:navigate>{{ __('Cancelar') }}</flux:button>
            </div>
        </form>
    </flux:card>
</div>
