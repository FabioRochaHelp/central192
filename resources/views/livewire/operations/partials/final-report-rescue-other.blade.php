<flux:card class="space-y-6">
    <flux:subheading>{{ __('Salvamento') }}</flux:subheading>

    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
        <flux:field>
            <flux:label>{{ __('Tipo de salvamento') }} *</flux:label>
            <flux:select wire:model="ro_rescue_subtype" placeholder="{{ __('Selecione') }}">
                <flux:select.option value="aquatico">{{ __('Aquático') }}</flux:select.option>
                <flux:select.option value="altura">{{ __('Em altura') }}</flux:select.option>
                <flux:select.option value="colapso_estrutural">{{ __('Colapso estrutural') }}</flux:select.option>
                <flux:select.option value="desencarceramento">{{ __('Desencarceramento veicular') }}</flux:select.option>
                <flux:select.option value="espaco_confinado">{{ __('Espaço confinado') }}</flux:select.option>
                <flux:select.option value="elevador">{{ __('Elevador') }}</flux:select.option>
                <flux:select.option value="outro">{{ __('Outro') }}</flux:select.option>
            </flux:select>
            <flux:error name="ro_rescue_subtype" />
        </flux:field>

        <flux:field>
            <flux:label>{{ __('Número de vítimas') }}</flux:label>
            <flux:input type="number" wire:model="ro_victim_count" min="1" />
            <flux:error name="ro_victim_count" />
        </flux:field>

        <flux:field>
            <flux:label>{{ __('Condição da vítima') }} *</flux:label>
            <flux:select wire:model="ro_victim_condition" placeholder="{{ __('Selecione') }}">
                <flux:select.option value="ileso">{{ __('Ileso') }}</flux:select.option>
                <flux:select.option value="ferido_leve">{{ __('Ferido leve') }}</flux:select.option>
                <flux:select.option value="ferido_grave">{{ __('Ferido grave') }}</flux:select.option>
                <flux:select.option value="obito">{{ __('Óbito') }}</flux:select.option>
            </flux:select>
            <flux:error name="ro_victim_condition" />
        </flux:field>

        <flux:field>
            <flux:label>{{ __('Desfecho') }} *</flux:label>
            <flux:select wire:model="ro_outcome" placeholder="{{ __('Selecione') }}">
                <flux:select.option value="resgatado_ileso">{{ __('Resgatado — ileso') }}</flux:select.option>
                <flux:select.option value="resgatado_ferido">{{ __('Resgatado — ferido') }}</flux:select.option>
                <flux:select.option value="obito_local">{{ __('Óbito no local') }}</flux:select.option>
                <flux:select.option value="nao_localizado">{{ __('Não localizado') }}</flux:select.option>
            </flux:select>
            <flux:error name="ro_outcome" />
        </flux:field>

        <flux:field>
            <flux:label>{{ __('Duração da operação (min)') }}</flux:label>
            <flux:input type="number" wire:model="ro_duration_minutes" min="1" />
            <flux:error name="ro_duration_minutes" />
        </flux:field>
    </div>

    <flux:textarea wire:model="ro_situation_description" :label="__('Descrição da situação encontrada')" rows="3" />
    <flux:error name="ro_situation_description" />

    <flux:textarea wire:model="ro_entrapment_description" :label="__('Como a vítima estava presa / em risco')" rows="2" />

    <flux:textarea wire:model="ro_rescue_technique" :label="__('Técnica de salvamento utilizada')" rows="2" />
    <flux:error name="ro_rescue_technique" />

    <flux:textarea wire:model="ro_equipment_used" :label="__('Equipamentos e materiais')" rows="2" />

    <div class="space-y-3">
        <flux:field variant="inline">
            <flux:checkbox wire:model="ro_hospital_transport" />
            <flux:label>{{ __('Vítima transportada para hospital') }}</flux:label>
        </flux:field>

        @if ($ro_hospital_transport)
            <flux:field>
                <flux:label>{{ __('Hospital de destino') }}</flux:label>
                <flux:input wire:model="ro_hospital_name" />
                <flux:error name="ro_hospital_name" />
            </flux:field>
        @endif
    </div>
</flux:card>
