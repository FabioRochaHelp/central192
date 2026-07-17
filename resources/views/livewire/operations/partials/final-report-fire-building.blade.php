<flux:card class="space-y-6">
    <flux:subheading>{{ __('Incêndio em edificação') }}</flux:subheading>

    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
        <flux:field>
            <flux:label>{{ __('Tipo de edificação') }}</flux:label>
            <flux:select wire:model="fb_building_type" placeholder="{{ __('Selecione') }}">
                <flux:select.option value="residencial">{{ __('Residencial') }}</flux:select.option>
                <flux:select.option value="comercial">{{ __('Comercial') }}</flux:select.option>
                <flux:select.option value="industrial">{{ __('Industrial') }}</flux:select.option>
                <flux:select.option value="institucional">{{ __('Institucional') }}</flux:select.option>
                <flux:select.option value="misto">{{ __('Misto') }}</flux:select.option>
                <flux:select.option value="veiculo">{{ __('Veículo') }}</flux:select.option>
            </flux:select>
            <flux:error name="fb_building_type" />
        </flux:field>

        <flux:field>
            <flux:label>{{ __('Tipo de construção') }}</flux:label>
            <flux:select wire:model="fb_construction_type" placeholder="{{ __('Selecione') }}">
                <flux:select.option value="alvenaria">{{ __('Alvenaria') }}</flux:select.option>
                <flux:select.option value="madeira">{{ __('Madeira') }}</flux:select.option>
                <flux:select.option value="metalica">{{ __('Metálica') }}</flux:select.option>
                <flux:select.option value="misto">{{ __('Misto') }}</flux:select.option>
            </flux:select>
            <flux:error name="fb_construction_type" />
        </flux:field>

        <flux:field>
            <flux:label>{{ __('Situação final') }} *</flux:label>
            <flux:select wire:model="fb_final_status" placeholder="{{ __('Selecione') }}">
                <flux:select.option value="extinto">{{ __('Extinto') }}</flux:select.option>
                <flux:select.option value="controlado">{{ __('Controlado') }}</flux:select.option>
                <flux:select.option value="monitoramento">{{ __('Em monitoramento') }}</flux:select.option>
                <flux:select.option value="transferido">{{ __('Transferido') }}</flux:select.option>
            </flux:select>
            <flux:error name="fb_final_status" />
        </flux:field>

        <flux:field>
            <flux:label>{{ __('Andares totais') }}</flux:label>
            <flux:input type="number" wire:model="fb_floors_total" min="1" />
            <flux:error name="fb_floors_total" />
        </flux:field>

        <flux:field>
            <flux:label>{{ __('Andares atingidos') }}</flux:label>
            <flux:input type="number" wire:model="fb_floors_affected" min="0" />
            <flux:error name="fb_floors_affected" />
        </flux:field>

        <flux:field>
            <flux:label>{{ __('Área atingida (m²)') }}</flux:label>
            <flux:input type="number" step="0.01" wire:model="fb_affected_area_m2" min="0" />
            <flux:error name="fb_affected_area_m2" />
        </flux:field>

        <flux:field>
            <flux:label>{{ __('Causa provável') }}</flux:label>
            <flux:select wire:model="fb_probable_cause" placeholder="{{ __('Selecione') }}">
                <flux:select.option value="falha_eletrica">{{ __('Falha elétrica') }}</flux:select.option>
                <flux:select.option value="vazamento_gas">{{ __('Vazamento de gás') }}</flux:select.option>
                <flux:select.option value="descuido">{{ __('Descuido') }}</flux:select.option>
                <flux:select.option value="criminoso">{{ __('Criminoso') }}</flux:select.option>
                <flux:select.option value="explosao">{{ __('Explosão') }}</flux:select.option>
                <flux:select.option value="curto">{{ __('Curto-circuito') }}</flux:select.option>
                <flux:select.option value="indeterminado">{{ __('Indeterminado') }}</flux:select.option>
            </flux:select>
            <flux:error name="fb_probable_cause" />
        </flux:field>

        <flux:field>
            <flux:label>{{ __('Grau de dano') }}</flux:label>
            <flux:select wire:model="fb_damage_level" placeholder="{{ __('Selecione') }}">
                <flux:select.option value="parcial_leve">{{ __('Parcial leve (<25%)') }}</flux:select.option>
                <flux:select.option value="parcial_grave">{{ __('Parcial grave (26–75%)') }}</flux:select.option>
                <flux:select.option value="total">{{ __('Total (>75%)') }}</flux:select.option>
            </flux:select>
            <flux:error name="fb_damage_level" />
        </flux:field>

        <flux:field>
            <flux:label>{{ __('Origem do incêndio') }}</flux:label>
            <flux:input wire:model="fb_fire_origin_location" placeholder="{{ __('Cômodo / setor') }}" />
            <flux:error name="fb_fire_origin_location" />
        </flux:field>
    </div>

    <div class="grid gap-4 sm:grid-cols-4">
        <flux:field>
            <flux:label>{{ __('Ocupantes presentes') }}</flux:label>
            <flux:input type="number" wire:model="fb_occupants_at_incident" min="0" />
            <flux:error name="fb_occupants_at_incident" />
        </flux:field>
        <flux:field>
            <flux:label>{{ __('Animais resgatados') }}</flux:label>
            <flux:input type="number" wire:model="fb_animals_rescued" min="0" />
        </flux:field>
        <flux:field>
            <flux:label>{{ __('Animais mortos') }}</flux:label>
            <flux:input type="number" wire:model="fb_animals_deceased" min="0" />
        </flux:field>
        <flux:field>
            <flux:label>{{ __('Desabrigados') }}</flux:label>
            <flux:input type="number" wire:model="fb_residents_displaced" min="0" />
        </flux:field>
    </div>

    <div class="flex flex-wrap gap-6">
        <flux:field variant="inline">
            <flux:checkbox wire:model="fb_hazmat_present" />
            <flux:label>{{ __('Produtos perigosos presentes') }}</flux:label>
        </flux:field>
        <flux:field variant="inline">
            <flux:checkbox wire:model="fb_vehicle_involved" />
            <flux:label>{{ __('Veículo envolvido') }}</flux:label>
        </flux:field>
    </div>

    @if ($fb_hazmat_present)
        <flux:textarea wire:model="fb_hazmat_description" :label="__('Descrição dos produtos perigosos')" rows="2"
            placeholder="{{ __('Inflamáveis, GLP, produtos químicos…') }}" />
        <flux:error name="fb_hazmat_description" />
    @endif

    <div class="grid gap-4 sm:grid-cols-2">
        <flux:input wire:model="fb_business_name" :label="__('Razão social / nome do estabelecimento')"
            placeholder="{{ __('Para uso comercial / industrial') }}" />
        <flux:input wire:model="fb_business_activity" :label="__('Ramo de atividade')" />
    </div>

    <flux:textarea wire:model="fb_external_agencies" :label="__('Agências externas')" rows="2"
        placeholder="{{ __('Concessionária de gás, CELESC/COPEL, PM…') }}" />

    <flux:textarea wire:model="fb_actions_taken" :label="__('Ações realizadas')" rows="4"
        placeholder="{{ __('Combate, busca e salvamento, ventilação, rescaldo, isolamento…') }}" />
</flux:card>
