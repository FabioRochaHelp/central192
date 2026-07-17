<flux:modal wire:model.self="showClosureModal" wire:close="closeClosureModal" class="max-w-lg">
    @if ($closureIncident !== null)
        <div class="space-y-4">
            <div>
                <flux:heading size="lg">{{ __('Encerrar ocorrência') }}</flux:heading>
                <flux:text class="mt-1 text-zinc-600 dark:text-zinc-400">
                    {{ __('Todas as viaturas retornaram à base. Informe qual foi a viatura principal desta ocorrência.') }}
                </flux:text>
            </div>

            <flux:callout variant="info">
                {{ __('Talão :talao/:ano', ['talao' => $closureIncident->talao, 'ano' => $closureIncident->dispatch_year]) }}
            </flux:callout>

            @error('board')
                <flux:callout variant="danger" class="text-sm">{{ $message }}</flux:callout>
            @enderror

            @error('closurePrimaryShiftId')
                <flux:callout variant="danger" class="text-sm">{{ $message }}</flux:callout>
            @enderror

            <flux:radio.group wire:model="closurePrimaryShiftId" :label="__('Viatura principal')">
                @foreach ($closureCandidates as $candidate)
                    <flux:radio
                        wire:key="closure-shift-{{ $candidate->shift_id }}"
                        value="{{ $candidate->shift_id }}"
                        :label="$candidate->shift?->vehicle?->prefix ?? __('Viatura')"
                        :description="$candidate->shift?->vehicle?->plate"
                    />
                @endforeach
            </flux:radio.group>

            <div class="flex flex-wrap gap-2">
                <flux:button
                    variant="primary"
                    color="emerald"
                    icon="check-circle"
                    wire:click="finalizeIncidentClosure"
                    wire:loading.attr="disabled"
                >
                    {{ __('Confirmar encerramento') }}
                </flux:button>
                <flux:button variant="outline" wire:click="closeClosureModal">
                    {{ __('Cancelar') }}
                </flux:button>
            </div>
        </div>
    @endif
</flux:modal>
