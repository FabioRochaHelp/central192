<flux:card class="space-y-6">
    <flux:subheading>{{ __('Salvamento de animal') }}</flux:subheading>

    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
        <flux:field>
            <flux:label>{{ __('Categoria') }} *</flux:label>
            <flux:select wire:model="ra_animal_category" placeholder="{{ __('Selecione') }}">
                <flux:select.option value="domestico">{{ __('Doméstico') }}</flux:select.option>
                <flux:select.option value="silvestre">{{ __('Silvestre') }}</flux:select.option>
                <flux:select.option value="de_producao">{{ __('De produção') }}</flux:select.option>
            </flux:select>
            <flux:error name="ra_animal_category" />
        </flux:field>

        <flux:field>
            <flux:label>{{ __('Espécie') }} *</flux:label>
            <flux:select wire:model="ra_animal_species" placeholder="{{ __('Selecione') }}">
                <flux:select.option value="cao">{{ __('Cão') }}</flux:select.option>
                <flux:select.option value="gato">{{ __('Gato') }}</flux:select.option>
                <flux:select.option value="cavalo">{{ __('Cavalo') }}</flux:select.option>
                <flux:select.option value="boi">{{ __('Boi/Bovino') }}</flux:select.option>
                <flux:select.option value="serpente">{{ __('Serpente') }}</flux:select.option>
                <flux:select.option value="ave">{{ __('Ave') }}</flux:select.option>
                <flux:select.option value="jacare">{{ __('Jacaré') }}</flux:select.option>
                <flux:select.option value="outro">{{ __('Outro') }}</flux:select.option>
            </flux:select>
            <flux:error name="ra_animal_species" />
        </flux:field>

        <flux:field>
            <flux:label>{{ __('Raça / Descrição') }}</flux:label>
            <flux:input wire:model="ra_animal_breed" placeholder="{{ __('Ex.: labrador, anaconda…') }}" />
            <flux:error name="ra_animal_breed" />
        </flux:field>

        <flux:field>
            <flux:label>{{ __('Porte') }}</flux:label>
            <flux:select wire:model="ra_animal_size" placeholder="{{ __('—') }}">
                <flux:select.option value="pequeno">{{ __('Pequeno') }}</flux:select.option>
                <flux:select.option value="medio">{{ __('Médio') }}</flux:select.option>
                <flux:select.option value="grande">{{ __('Grande') }}</flux:select.option>
            </flux:select>
            <flux:error name="ra_animal_size" />
        </flux:field>

        <flux:field>
            <flux:label>{{ __('Tipo de aprisionamento') }} *</flux:label>
            <flux:select wire:model="ra_entrapment_type" placeholder="{{ __('Selecione') }}">
                <flux:select.option value="arvore">{{ __('Árvore') }}</flux:select.option>
                <flux:select.option value="buraco">{{ __('Buraco / Vala') }}</flux:select.option>
                <flux:select.option value="cisterna_poco">{{ __('Cisterna / Poço') }}</flux:select.option>
                <flux:select.option value="via_aquatica">{{ __('Via aquática') }}</flux:select.option>
                <flux:select.option value="veiculo">{{ __('Veículo') }}</flux:select.option>
                <flux:select.option value="estrutura">{{ __('Estrutura') }}</flux:select.option>
                <flux:select.option value="cerca_cabo">{{ __('Cerca / Cabo') }}</flux:select.option>
                <flux:select.option value="elevado">{{ __('Local elevado') }}</flux:select.option>
                <flux:select.option value="outro">{{ __('Outro') }}</flux:select.option>
            </flux:select>
            <flux:error name="ra_entrapment_type" />
        </flux:field>

        <flux:field>
            <flux:label>{{ __('Altura estimada (m)') }}</flux:label>
            <flux:input type="number" wire:model="ra_entrapment_height_m" min="0" placeholder="{{ __('Se aplicável') }}" />
            <flux:error name="ra_entrapment_height_m" />
        </flux:field>

        <flux:field>
            <flux:label>{{ __('Condição na chegada') }} *</flux:label>
            <flux:select wire:model="ra_animal_condition_arrival" placeholder="{{ __('Selecione') }}">
                <flux:select.option value="calmo">{{ __('Calmo') }}</flux:select.option>
                <flux:select.option value="agitado">{{ __('Agitado') }}</flux:select.option>
                <flux:select.option value="ferido">{{ __('Ferido') }}</flux:select.option>
                <flux:select.option value="inconsciente">{{ __('Inconsciente') }}</flux:select.option>
                <flux:select.option value="obito_chegada">{{ __('Óbito na chegada') }}</flux:select.option>
            </flux:select>
            <flux:error name="ra_animal_condition_arrival" />
        </flux:field>

        <flux:field>
            <flux:label>{{ __('Desfecho') }} *</flux:label>
            <flux:select wire:model="ra_outcome" placeholder="{{ __('Selecione') }}">
                <flux:select.option value="resgatado_tutor">{{ __('Resgatado — devolvido ao tutor') }}</flux:select.option>
                <flux:select.option value="resgatado_abrigo">{{ __('Resgatado — encaminhado a abrigo') }}</flux:select.option>
                <flux:select.option value="resgatado_veterinario">{{ __('Resgatado — encaminhado a veterinário') }}</flux:select.option>
                <flux:select.option value="solto_silvestre">{{ __('Solto em área natural') }}</flux:select.option>
                <flux:select.option value="nao_localizado">{{ __('Não localizado') }}</flux:select.option>
                <flux:select.option value="obito">{{ __('Óbito') }}</flux:select.option>
            </flux:select>
            <flux:error name="ra_outcome" />
        </flux:field>
    </div>

    <flux:subheading size="sm">{{ __('Tutor / Responsável') }}</flux:subheading>
    <div class="grid gap-4 sm:grid-cols-2">
        <flux:field>
            <flux:label>{{ __('Nome') }}</flux:label>
            <flux:input wire:model="ra_owner_name" />
            <flux:error name="ra_owner_name" />
        </flux:field>
        <flux:field>
            <flux:label>{{ __('Telefone') }}</flux:label>
            <flux:input wire:model="ra_owner_phone" />
            <flux:error name="ra_owner_phone" />
        </flux:field>
    </div>

    <flux:textarea wire:model="ra_equipment_used" :label="__('Equipamentos utilizados')" rows="2"
        placeholder="{{ __('Escada, corda, rede, armadilha, alicate, EPI…') }}" />

    <flux:textarea wire:model="ra_destination_notes" :label="__('Observações sobre destino')" rows="2" />
</flux:card>
