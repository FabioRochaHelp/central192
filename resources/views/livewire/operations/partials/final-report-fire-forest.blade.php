<flux:card class="space-y-6">
    <flux:subheading>{{ __('Incêndio florestal') }}</flux:subheading>

    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
        <flux:field>
            <flux:label>{{ __('Área atingida (ha)') }}</flux:label>
            <flux:input type="number" step="0.01" wire:model="ff_affected_area_ha" min="0" />
            <flux:error name="ff_affected_area_ha" />
        </flux:field>

        <flux:field>
            <flux:label>{{ __('Tipo de vegetação') }}</flux:label>
            <flux:select wire:model="ff_vegetation_type" placeholder="{{ __('Selecione') }}">
                <flux:select.option value="cerrado">{{ __('Cerrado') }}</flux:select.option>
                <flux:select.option value="mata_atlantica">{{ __('Mata atlântica') }}</flux:select.option>
                <flux:select.option value="pasto">{{ __('Pasto') }}</flux:select.option>
                <flux:select.option value="capoeira">{{ __('Capoeira') }}</flux:select.option>
                <flux:select.option value="eucalipto">{{ __('Eucalipto') }}</flux:select.option>
                <flux:select.option value="outro">{{ __('Outro') }}</flux:select.option>
            </flux:select>
            <flux:error name="ff_vegetation_type" />
        </flux:field>

        <flux:field>
            <flux:label>{{ __('Comportamento do fogo') }}</flux:label>
            <flux:select wire:model="ff_fire_behavior" placeholder="{{ __('Selecione') }}">
                <flux:select.option value="superficial">{{ __('Superficial') }}</flux:select.option>
                <flux:select.option value="copa">{{ __('Copa') }}</flux:select.option>
                <flux:select.option value="salto">{{ __('Salto (spotting)') }}</flux:select.option>
                <flux:select.option value="misto">{{ __('Misto') }}</flux:select.option>
            </flux:select>
            <flux:error name="ff_fire_behavior" />
        </flux:field>

        <flux:field>
            <flux:label>{{ __('Causa provável') }}</flux:label>
            <flux:select wire:model="ff_probable_cause" placeholder="{{ __('Selecione') }}">
                <flux:select.option value="raio">{{ __('Raio') }}</flux:select.option>
                <flux:select.option value="descuido_humano">{{ __('Descuido humano') }}</flux:select.option>
                <flux:select.option value="criminoso">{{ __('Criminoso') }}</flux:select.option>
                <flux:select.option value="operacional">{{ __('Operacional') }}</flux:select.option>
                <flux:select.option value="indeterminado">{{ __('Indeterminado') }}</flux:select.option>
            </flux:select>
            <flux:error name="ff_probable_cause" />
        </flux:field>

        <flux:field>
            <flux:label>{{ __('Fonte de descoberta') }}</flux:label>
            <flux:select wire:model="ff_discovery_source" placeholder="{{ __('Selecione') }}">
                <flux:select.option value="vigilancia_aerea">{{ __('Vigilância aérea') }}</flux:select.option>
                <flux:select.option value="denuncia">{{ __('Denúncia') }}</flux:select.option>
                <flux:select.option value="inpe">{{ __('INPE') }}</flux:select.option>
                <flux:select.option value="rondante">{{ __('Rondante') }}</flux:select.option>
                <flux:select.option value="outro">{{ __('Outro') }}</flux:select.option>
            </flux:select>
            <flux:error name="ff_discovery_source" />
        </flux:field>

        <flux:field>
            <flux:label>{{ __('Situação final') }} *</flux:label>
            <flux:select wire:model="ff_final_status" placeholder="{{ __('Selecione') }}">
                <flux:select.option value="extinto">{{ __('Extinto') }}</flux:select.option>
                <flux:select.option value="controlado">{{ __('Controlado') }}</flux:select.option>
                <flux:select.option value="monitoramento">{{ __('Em monitoramento') }}</flux:select.option>
                <flux:select.option value="repassado">{{ __('Repassado') }}</flux:select.option>
            </flux:select>
            <flux:error name="ff_final_status" />
        </flux:field>
    </div>

    <div class="flex flex-wrap items-center justify-between gap-2">
        <flux:subheading size="sm">{{ __('Condições meteorológicas') }}</flux:subheading>
        <div class="flex items-center gap-3">
            @if ($weatherFetchStatus === 'ok')
                <flux:text size="xs" class="text-green-600 dark:text-green-400">
                    {{ __('Dados obtidos via Open-Meteo') }}
                </flux:text>
            @elseif ($weatherFetchStatus === 'no_location')
                <flux:text size="xs" class="text-amber-600 dark:text-amber-400">
                    {{ __('Ocorrência sem coordenadas ou cidade cadastrada') }}
                </flux:text>
            @elseif ($weatherFetchStatus === 'error')
                <flux:text size="xs" class="text-red-600 dark:text-red-400">
                    {{ __('Não foi possível obter dados meteorológicos') }}
                </flux:text>
            @endif
            <flux:button
                size="sm"
                variant="primary"
                icon="cloud-arrow-down"
                wire:click="loadWeather"
                wire:loading.attr="disabled"
                wire:target="loadWeather"
            >
                <span wire:loading.remove wire:target="loadWeather">{{ __('Buscar via Open-Meteo') }}</span>
                <span wire:loading wire:target="loadWeather">{{ __('Buscando…') }}</span>
            </flux:button>
        </div>
    </div>
    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <flux:field>
            <flux:label>{{ __('Temperatura (°C)') }}</flux:label>
            <flux:input type="number" wire:model="ff_temperature_celsius" />
            <flux:error name="ff_temperature_celsius" />
        </flux:field>
        <flux:field>
            <flux:label>{{ __('Umidade (%)') }}</flux:label>
            <flux:input type="number" wire:model="ff_humidity_percent" min="0" max="100" />
            <flux:error name="ff_humidity_percent" />
        </flux:field>
        <flux:field>
            <flux:label>{{ __('Vel. vento (km/h)') }}</flux:label>
            <flux:input type="number" wire:model="ff_wind_speed_kmh" min="0" />
            <flux:error name="ff_wind_speed_kmh" />
        </flux:field>
        <flux:field>
            <flux:label>{{ __('Direção do vento') }}</flux:label>
            <flux:select wire:model="ff_wind_direction" placeholder="{{ __('—') }}">
                @foreach (['N','NE','L','SE','S','SO','O','NO'] as $dir)
                    <flux:select.option value="{{ $dir }}">{{ $dir }}</flux:select.option>
                @endforeach
            </flux:select>
            <flux:error name="ff_wind_direction" />
        </flux:field>
    </div>

    <flux:subheading size="sm">{{ __('Recursos e danos') }}</flux:subheading>
    <div class="grid gap-4 sm:grid-cols-3">
        <flux:field>
            <flux:label>{{ __('Efetivo empregado') }}</flux:label>
            <flux:input type="number" wire:model="ff_personnel_count" min="0" />
            <flux:error name="ff_personnel_count" />
        </flux:field>
        <flux:field>
            <flux:label>{{ __('Estruturas atingidas') }}</flux:label>
            <flux:input type="number" wire:model="ff_structures_affected" min="0" />
            <flux:error name="ff_structures_affected" />
        </flux:field>
        <flux:field>
            <flux:label>{{ __('Pessoas evacuadas') }}</flux:label>
            <flux:input type="number" wire:model="ff_people_evacuated" min="0" />
            <flux:error name="ff_people_evacuated" />
        </flux:field>
    </div>

    <div class="flex flex-wrap gap-6">
        <flux:field variant="inline">
            <flux:checkbox wire:model="ff_aircraft_used" />
            <flux:label>{{ __('Aeronave utilizada') }}</flux:label>
        </flux:field>
        <flux:field variant="inline">
            <flux:checkbox wire:model="ff_fauna_damage" />
            <flux:label>{{ __('Dano à fauna') }}</flux:label>
        </flux:field>
    </div>

    @if ($ff_aircraft_used)
        <flux:textarea wire:model="ff_aircraft_description" :label="__('Descrição da aeronave')" rows="2" />
        <flux:error name="ff_aircraft_description" />
    @endif

    @if ($ff_fauna_damage)
        <flux:textarea wire:model="ff_fauna_damage_description" :label="__('Descrição do dano à fauna')" rows="2" />
        <flux:error name="ff_fauna_damage_description" />
    @endif

    <flux:field>
        <flux:label>{{ __('Apoios externos acionados') }}</flux:label>
        @if ($availableSupports->isEmpty())
            <flux:text size="sm" class="text-zinc-500">{{ __('Nenhum apoio cadastrado para este município.') }}</flux:text>
        @else
            <div class="mt-1 max-h-44 overflow-y-auto rounded-lg border border-zinc-200 p-2 dark:border-zinc-700">
                @foreach ($availableSupports as $support)
                    <label class="flex cursor-pointer items-center gap-2 rounded px-2 py-1 hover:bg-zinc-50 dark:hover:bg-zinc-800">
                        <flux:checkbox wire:model="ff_external_agencies" value="{{ $support->id }}" />
                        <span class="text-sm">{{ $support->name }}</span>
                    </label>
                @endforeach
            </div>
        @endif
        <flux:error name="ff_external_agencies" />
    </flux:field>

    <flux:textarea wire:model="ff_actions_taken" :label="__('Ações realizadas')" rows="4"
        placeholder="{{ __('Aceiro, contrafogo, abafamento, retardante…') }}" />

    <flux:separator />

    <div class="space-y-3">
        <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <flux:label>{{ __('Cicatriz de incêndio (área queimada)') }}</flux:label>
                <flux:text size="sm" class="text-zinc-500">{{ __('Solicita ao serviço de sensoriamento remoto a estimativa de área queimada e severidade para a coordenada da ocorrência.') }}</flux:text>
            </div>
            <flux:button type="button" size="sm" variant="filled" icon="fire" wire:click="requestFireScar" wire:loading.attr="disabled" wire:target="requestFireScar">
                {{ __('Solicitar cicatriz') }}
            </flux:button>
        </div>

        <flux:error name="fireScar" />

        @if ($fireScarMessage)
            <flux:callout variant="success">{{ $fireScarMessage }}</flux:callout>
        @endif

        @if ($fireScars->isNotEmpty())
            <div class="overflow-x-auto rounded-lg border border-zinc-200 dark:border-zinc-700">
                <table class="min-w-full divide-y divide-zinc-200 text-sm dark:divide-zinc-700">
                    <thead class="bg-zinc-50 dark:bg-zinc-900/50">
                        <tr>
                            <th class="px-3 py-2 text-start font-medium">{{ __('Status') }}</th>
                            <th class="px-3 py-2 text-start font-medium">{{ __('Severidade') }}</th>
                            <th class="px-3 py-2 text-end font-medium">{{ __('Área (ha)') }}</th>
                            <th class="px-3 py-2"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800">
                        @foreach ($fireScars as $scar)
                            <tr wire:key="ff-scar-{{ $scar->id }}">
                                <td class="px-3 py-2">{{ $scar->status->label() }}</td>
                                <td class="px-3 py-2">{{ $scar->severity()?->label() ?? '—' }}</td>
                                <td class="px-3 py-2 text-end">{{ $scar->area_ha !== null ? number_format((float) $scar->area_ha, 2, ',', '.') : '—' }}</td>
                                <td class="px-3 py-2 text-end">
                                    <flux:button size="xs" variant="ghost" icon="eye" :href="route('operations.reports.fire-scars.show', $scar)">{{ __('Ver') }}</flux:button>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</flux:card>
