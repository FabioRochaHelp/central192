<flux:card class="space-y-6">
    <flux:subheading>{{ __('Insetos agressivos') }}</flux:subheading>

    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
        <flux:field>
            <flux:label>{{ __('Tipo de inseto') }} *</flux:label>
            <flux:select wire:model="ri_insect_type" placeholder="{{ __('Selecione') }}">
                <flux:select.option value="abelhas">{{ __('Abelhas') }}</flux:select.option>
                <flux:select.option value="marimbondos">{{ __('Marimbondos') }}</flux:select.option>
                <flux:select.option value="vespas">{{ __('Vespas') }}</flux:select.option>
                <flux:select.option value="maribondo_tatu">{{ __('Maribondo-tatu') }}</flux:select.option>
                <flux:select.option value="outro">{{ __('Outro') }}</flux:select.option>
            </flux:select>
            <flux:error name="ri_insect_type" />
        </flux:field>

        <flux:field>
            <flux:label>{{ __('Espécie (científica)') }}</flux:label>
            <flux:input wire:model="ri_insect_species" placeholder="{{ __('Ex.: Apis mellifera') }}" />
            <flux:error name="ri_insect_species" />
        </flux:field>

        <flux:field>
            <flux:label>{{ __('Tamanho da colônia') }}</flux:label>
            <flux:select wire:model="ri_colony_size_estimate" placeholder="{{ __('—') }}">
                <flux:select.option value="pequena">{{ __('Pequena (< 5 mil)') }}</flux:select.option>
                <flux:select.option value="media">{{ __('Média (5–20 mil)') }}</flux:select.option>
                <flux:select.option value="grande">{{ __('Grande (> 20 mil)') }}</flux:select.option>
                <flux:select.option value="indeterminada">{{ __('Indeterminada') }}</flux:select.option>
            </flux:select>
            <flux:error name="ri_colony_size_estimate" />
        </flux:field>

        <flux:field>
            <flux:label>{{ __('Local do ninho') }} *</flux:label>
            <flux:select wire:model="ri_nest_location_type" placeholder="{{ __('Selecione') }}">
                <flux:select.option value="parede_forro">{{ __('Parede / Forro') }}</flux:select.option>
                <flux:select.option value="arvore">{{ __('Árvore') }}</flux:select.option>
                <flux:select.option value="subsolo">{{ __('Subsolo') }}</flux:select.option>
                <flux:select.option value="veiculo">{{ __('Veículo') }}</flux:select.option>
                <flux:select.option value="caixa_luz_agua">{{ __('Caixa de luz / Água') }}</flux:select.option>
                <flux:select.option value="estrutura_metalica">{{ __('Estrutura metálica') }}</flux:select.option>
                <flux:select.option value="outro">{{ __('Outro') }}</flux:select.option>
            </flux:select>
            <flux:error name="ri_nest_location_type" />
        </flux:field>

        <flux:field>
            <flux:label>{{ __('Técnica utilizada') }} *</flux:label>
            <flux:select wire:model="ri_technique_used" placeholder="{{ __('Selecione') }}">
                <flux:select.option value="captura_realocacao">{{ __('Captura e realocação') }}</flux:select.option>
                <flux:select.option value="exterminacao_quimica">{{ __('Exterminação química') }}</flux:select.option>
                <flux:select.option value="exterminacao_fisica">{{ __('Exterminação física') }}</flux:select.option>
                <flux:select.option value="nao_realizado">{{ __('Não realizado') }}</flux:select.option>
            </flux:select>
            <flux:error name="ri_technique_used" />
        </flux:field>

        <flux:field>
            <flux:label>{{ __('Destino da colônia') }}</flux:label>
            <flux:select wire:model="ri_colony_destination" placeholder="{{ __('—') }}">
                <flux:select.option value="apicultor">{{ __('Entregue a apicultor') }}</flux:select.option>
                <flux:select.option value="exterminada">{{ __('Exterminada') }}</flux:select.option>
                <flux:select.option value="realocada">{{ __('Realocada') }}</flux:select.option>
                <flux:select.option value="abandono_local">{{ __('Abandono no local') }}</flux:select.option>
            </flux:select>
            <flux:error name="ri_colony_destination" />
        </flux:field>
    </div>

    <flux:subheading size="sm">{{ __('Vítimas de picadas') }}</flux:subheading>
    <div class="grid gap-4 sm:grid-cols-2">
        <flux:field>
            <flux:label>{{ __('Pessoas picadas') }}</flux:label>
            <flux:input type="number" wire:model="ri_people_stung" min="0" />
            <flux:error name="ri_people_stung" />
        </flux:field>
        <flux:field>
            <flux:label>{{ __('Gravidade das picadas') }}</flux:label>
            <flux:select wire:model="ri_sting_severity" placeholder="{{ __('—') }}">
                <flux:select.option value="sem_atendimento">{{ __('Sem necessidade de atendimento') }}</flux:select.option>
                <flux:select.option value="leve">{{ __('Leve') }}</flux:select.option>
                <flux:select.option value="moderado_hospitalar">{{ __('Moderado — hospitalar') }}</flux:select.option>
                <flux:select.option value="grave">{{ __('Grave') }}</flux:select.option>
            </flux:select>
            <flux:error name="ri_sting_severity" />
        </flux:field>
    </div>

    <flux:field variant="inline">
        <flux:checkbox wire:model="ri_prehospital_care" />
        <flux:label>{{ __('Prestou atendimento pré-hospitalar') }}</flux:label>
    </flux:field>

    @if ($ri_prehospital_care)
        <flux:textarea wire:model="ri_prehospital_description" :label="__('Descrição do atendimento')" rows="2" />
        <flux:error name="ri_prehospital_description" />
    @endif

    <flux:textarea wire:model="ri_nest_location_detail" :label="__('Detalhe do local do ninho')" rows="2" />

    <flux:textarea wire:model="ri_equipment_used" :label="__('Equipamentos utilizados')" rows="2"
        placeholder="{{ __('Traje apicultor, extintores, bomba vácuo, EPI…') }}" />
</flux:card>
