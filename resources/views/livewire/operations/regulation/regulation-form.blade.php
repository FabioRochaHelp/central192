<div class="cco-page-gap">
    <div class="flex flex-wrap items-center gap-3">
        <flux:button variant="ghost" icon="arrow-left" :href="route('operations.incidents.regulation.index')" wire:navigate>
            {{ __('Voltar à fila') }}
        </flux:button>
    </div>

    <div class="flex flex-col gap-2">
        <flux:heading size="xl">{{ __('Regulação médica') }}</flux:heading>
        <flux:text class="text-zinc-600 dark:text-zinc-400">
            {{ __('Talão :talao/:ano — registre a classificação e a decisão de regulação. O envio de recurso libera a ocorrência para o despacho.', ['talao' => $incident->talao, 'ano' => $incident->dispatch_year]) }}
        </flux:text>
    </div>

    @error('save')
        <flux:callout variant="danger">{{ $message }}</flux:callout>
    @enderror

    {{-- Resumo da solicitação (somente leitura) --}}
    <flux:card>
        <flux:heading size="lg" class="mb-3">{{ __('Solicitação') }}</flux:heading>
        <div class="grid gap-4 sm:grid-cols-2">
            <div>
                <flux:text class="text-xs uppercase text-zinc-500">{{ __('Natureza') }}</flux:text>
                <flux:text class="font-medium">{{ $incident->nature?->name ?? '—' }}</flux:text>
            </div>
            <div>
                <flux:text class="text-xs uppercase text-zinc-500">{{ __('Paciente') }}</flux:text>
                <flux:text class="font-medium">
                    {{ $incident->patient_name ?: __('Não identificado') }}
                    @if ($incident->patient_age)· {{ $incident->patient_age }} {{ __('anos') }}@endif
                    @if ($incident->patient_sex)· {{ $incident->patient_sex }}@endif
                </flux:text>
            </div>
            <div class="sm:col-span-2">
                <flux:text class="text-xs uppercase text-zinc-500">{{ __('Local') }}</flux:text>
                <flux:text class="font-medium">
                    {{ $incident->address_line }}{{ $incident->number ? ', '.$incident->number : '' }}{{ $incident->district ? ' — '.$incident->district : '' }}{{ $incident->city ? ' / '.$incident->city : '' }}
                </flux:text>
            </div>
            <div class="sm:col-span-2">
                <flux:text class="text-xs uppercase text-zinc-500">{{ __('Queixa / descrição') }}</flux:text>
                <flux:text class="whitespace-pre-line">{{ $incident->description ?: '—' }}</flux:text>
            </div>
        </div>
    </flux:card>

    <flux:card>
        <form wire:submit="save" class="grid gap-6">
            <flux:select wire:model="priority" :label="__('Prioridade (Manchester)')" placeholder="{{ __('Selecione a prioridade') }}">
                <flux:select.option value="">{{ __('Não classificado') }}</flux:select.option>
                @foreach ($priorities as $p)
                    <flux:select.option value="{{ $p->value }}">{{ $p->label() }}</flux:select.option>
                @endforeach
            </flux:select>

            <flux:textarea
                wire:model="diagnostic_hypothesis"
                :label="__('Hipótese diagnóstica')"
                rows="3"
                placeholder="{{ __('Impressão clínica a partir dos dados coletados.') }}"
            />

            <flux:select wire:model.live="decision" :label="__('Decisão / conduta')" required placeholder="{{ __('Selecione a conduta') }}">
                @foreach ($decisions as $d)
                    <flux:select.option value="{{ $d->value }}">{{ $d->label() }}</flux:select.option>
                @endforeach
            </flux:select>

            @if ($decision === \App\Domain\Operations\Enums\RegulationDecision::DispatchResource->value)
                <flux:select wire:model="recommended_resource" :label="__('Recurso indicado')" required placeholder="{{ __('Selecione o recurso') }}">
                    @foreach ($resources as $r)
                        <flux:select.option value="{{ $r->value }}">{{ $r->label() }}</flux:select.option>
                    @endforeach
                </flux:select>
                <flux:callout variant="secondary" icon="information-circle">
                    {{ __('Ao registrar, a ocorrência vai para a fila de despacho (CCO) para o despachador empenhar a viatura.') }}
                </flux:callout>
            @endif

            @if ($decision === \App\Domain\Operations\Enums\RegulationDecision::MedicalGuidance->value)
                <flux:textarea wire:model="guidance_notes" :label="__('Orientações ao solicitante')" required rows="4"
                    placeholder="{{ __('Orientações médicas prestadas; conduta domiciliar / encaminhamento.') }}" />
            @endif

            @if ($decision === \App\Domain\Operations\Enums\RegulationDecision::Transfer->value)
                <flux:input wire:model="transfer_target" :label="__('Destino da transferência')" required
                    placeholder="{{ __('UPA, hospital ou serviço de destino.') }}" />
                <flux:textarea wire:model="guidance_notes" :label="__('Observações')" rows="3" />
            @endif

            @if ($decision === \App\Domain\Operations\Enums\RegulationDecision::Refused->value)
                <flux:textarea wire:model="refusal_reason" :label="__('Motivo da recusa')" required rows="4"
                    placeholder="{{ __('Justificativa para não caracterização de urgência/emergência.') }}" />
            @endif

            <div class="flex flex-wrap gap-2">
                <flux:button type="submit" variant="primary" wire:loading.attr="disabled">{{ __('Registrar decisão') }}</flux:button>
                <flux:button variant="ghost" type="button" :href="route('operations.incidents.regulation.index')" wire:navigate>{{ __('Cancelar') }}</flux:button>
            </div>
        </form>
    </flux:card>
</div>
