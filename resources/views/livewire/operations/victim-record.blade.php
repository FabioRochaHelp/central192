<div class="cco-page-gap">
    <div class="flex flex-wrap items-center gap-3">
        <flux:button variant="ghost" icon="arrow-left" :href="route('operations.incidents.show', $incident)"
            wire:navigate>
            {{ __('Voltar à ocorrência') }}
        </flux:button>
    </div>

    <div>
        <flux:heading size="xl">{{ $victimModel ? __('Editar vítima') : __('Nova vítima') }}</flux:heading>
        <flux:text class="mt-1 text-zinc-600 dark:text-zinc-400">
            {{ __('Talão :talao/:ano — registro clínico (vitima + procedimentos, acessórios, ferimentos e sinais vitais).', ['talao' => $incident->talao, 'ano' => $incident->dispatch_year]) }}
        </flux:text>
    </div>

    @error('save')
        <flux:callout variant="danger">{{ $message }}</flux:callout>
    @enderror

    <form wire:submit="save" class="grid gap-6">
        <flux:card class="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
            <flux:subheading class="md:col-span-2 lg:col-span-3">{{ __('Dados básicos') }}</flux:subheading>

            <flux:input wire:model="name" :label="__('Nome')" class="md:col-span-2" />

            <flux:select wire:model="sex" :label="__('Sexo')" placeholder="{{ __('Obrigatório') }}">
                <flux:select.option value="">{{ __('—') }}</flux:select.option>
                <flux:select.option value="1">{{ __('Masculino') }}</flux:select.option>
                <flux:select.option value="2">{{ __('Feminino') }}</flux:select.option>
                <flux:select.option value="3">{{ __('Outro') }}</flux:select.option>
            </flux:select>

            <flux:input wire:model="age" type="number" :label="__('Idade')" />
            <flux:input wire:model="rg" :label="__('RG')" />
            <flux:input wire:model="ssp" :label="__('SSP / órgão emissor')" />

            <div class="space-y-2 md:col-span-2 lg:col-span-3">
                <flux:text size="sm" class="font-medium">{{ __('Situação') }}</flux:text>
                <div class="grid gap-2 sm:grid-cols-3">
                    <button type="button" wire:click="$set('situacao', '1')"
                        class="rounded-xl border px-4 py-3 text-start transition {{ $situacao === '1' ? 'border-blue-500 bg-blue-50 text-blue-800 ring-2 ring-blue-200 dark:border-blue-400 dark:bg-blue-950/40 dark:text-blue-100 dark:ring-blue-900' : 'border-zinc-200 hover:border-blue-300 hover:bg-blue-50 dark:border-zinc-700 dark:hover:border-blue-700 dark:hover:bg-blue-950/30' }}">
                        <span class="block font-medium">{{ __('Atendida') }}</span>
                        <span
                            class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('Exibe atendimento clínico completo.') }}</span>
                    </button>
                    <button type="button" wire:click="$set('situacao', '3')"
                        class="rounded-xl border px-4 py-3 text-start transition {{ $situacao === '3' ? 'border-amber-500 bg-amber-50 text-amber-800 ring-2 ring-amber-200 dark:border-amber-400 dark:bg-amber-950/40 dark:text-amber-100 dark:ring-amber-900' : 'border-zinc-200 hover:border-amber-300 hover:bg-amber-50 dark:border-zinc-700 dark:hover:border-amber-700 dark:hover:bg-amber-950/30' }}">
                        <span class="block font-medium">{{ __('Recusa de atendimento') }}</span>
                        <span
                            class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('Exibe testemunha e documentos.') }}</span>
                    </button>
                    <button type="button" wire:click="$set('situacao', '2')"
                        class="rounded-xl border px-4 py-3 text-start transition {{ $situacao === '2' ? 'border-zinc-600 bg-zinc-100 text-zinc-900 ring-2 ring-zinc-300 dark:border-zinc-300 dark:bg-zinc-800 dark:text-zinc-100 dark:ring-zinc-700' : 'border-zinc-200 hover:border-zinc-400 hover:bg-zinc-50 dark:border-zinc-700 dark:hover:border-zinc-500 dark:hover:bg-zinc-900' }}">
                        <span class="block font-medium">{{ __('Óbito') }}</span>
                        <span
                            class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('Exibe local e parecer do óbito.') }}</span>
                    </button>
                </div>
                @error('situacao')
                    <flux:text size="sm" class="text-red-600 dark:text-red-400">{{ $message }}</flux:text>
                @enderror
            </div>

            <flux:input wire:model="status" type="number" :label="__('Status (legado numérico)')"
                placeholder="{{ __('Opcional') }}" />
        </flux:card>

        @if ($situacao === '1')
            <flux:card class="space-y-4">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <flux:subheading>{{ __('Sinais vitais seriados') }}</flux:subheading>
                    <flux:button type="button" size="sm" variant="ghost" wire:click="addVitalRow" icon="plus">
                        {{ __('Adicionar linha') }}</flux:button>
                </div>
                @foreach ($vital_rows as $idx => $row)
                    <div wire:key="vit-{{ $idx }}"
                        class="rounded-xl border border-zinc-200 p-4 dark:border-zinc-700">
                        <div class="mb-3 flex items-center justify-between gap-2">
                            <flux:text size="sm" class="font-medium">{{ __('Medição :n', ['n' => $idx + 1]) }}
                            </flux:text>
                            @if (count($vital_rows) > 1)
                                <flux:button type="button" size="sm" variant="ghost"
                                    wire:click="removeVitalRow({{ $idx }})">{{ __('Remover') }}</flux:button>
                            @endif
                        </div>
                        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                            <flux:input wire:model="vital_rows.{{ $idx }}.recorded_at" type="datetime-local"
                                :label="__('Data/hora')" />
                            <flux:input wire:model="vital_rows.{{ $idx }}.blood_pressure_systolic"
                                type="number" :label="__('PAS')" />
                            <flux:input wire:model="vital_rows.{{ $idx }}.blood_pressure_diastolic"
                                type="number" :label="__('PAD')" />
                            <flux:input wire:model="vital_rows.{{ $idx }}.heart_rate" type="number"
                                :label="__('FC (bpm)')" />
                            <flux:input wire:model="vital_rows.{{ $idx }}.respiratory_rate" type="number"
                                :label="__('FR')" />
                            <flux:input wire:model="vital_rows.{{ $idx }}.spo2" type="number"
                                :label="__('SpO₂ %')" />
                            <flux:input wire:model="vital_rows.{{ $idx }}.temperature" type="number"
                                step="0.1" :label="__('Temp. °C')" />
                            <flux:input wire:model="vital_rows.{{ $idx }}.blood_glucose" type="number"
                                :label="__('Destro')" />
                            <flux:input wire:model.live="vital_rows.{{ $idx }}.glasgow_eye" type="number"
                                min="1" max="4" :label="__('RO')" />
                            <flux:input wire:model.live="vital_rows.{{ $idx }}.glasgow_verbal" type="number"
                                min="1" max="5" :label="__('RV')" />
                            <flux:input wire:model.live="vital_rows.{{ $idx }}.glasgow_motor" type="number"
                                min="1" max="6" :label="__('RM')" />
                            <div class="rounded-lg border border-zinc-200 px-3 py-2 dark:border-zinc-700">
                                <flux:text size="sm" class="text-zinc-500">{{ __('Glasgow') }}</flux:text>
                                <flux:text class="tabular-nums">{{ $this->glasgowTotalForRow($row) }}</flux:text>
                            </div>
                            <flux:select wire:model="vital_rows.{{ $idx }}.dominant_side"
                                :label="__('Lateralidade')">
                                <flux:select.option value="">{{ __('—') }}</flux:select.option>
                                <flux:select.option value="L">{{ __('Esquerdo') }}</flux:select.option>
                                <flux:select.option value="R">{{ __('Direito') }}</flux:select.option>
                            </flux:select>
                            <flux:textarea wire:model="vital_rows.{{ $idx }}.neurological_notes"
                                :label="__('Neurológico')" rows="2" class="sm:col-span-2 lg:col-span-4" />
                        </div>
                    </div>
                @endforeach
            </flux:card>
        @endif

        @if ($situacao === '1')
            <flux:card class="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
                <flux:subheading class="md:col-span-2 lg:col-span-3">{{ __('Classificação e cena') }}
                </flux:subheading>

                <flux:select wire:model="victim_type_id" :label="__('Tipo de vítima')"
                    placeholder="{{ __('Opcional') }}">
                    <flux:select.option value="">{{ __('—') }}</flux:select.option>
                    @foreach ($victimTypes as $vt)
                        <flux:select.option value="{{ $vt->id }}">{{ $vt->name }}</flux:select.option>
                    @endforeach
                </flux:select>

                <flux:select wire:model="care_local_id" :label="__('Local da cena')"
                    placeholder="{{ __('Opcional') }}">
                    <flux:select.option value="">{{ __('—') }}</flux:select.option>
                    @foreach ($careLocals as $cl)
                        <flux:select.option value="{{ $cl->id }}">{{ $cl->name }}</flux:select.option>
                    @endforeach
                </flux:select>
            </flux:card>
        @endif

        @if ($situacao === '1')
            <flux:card class="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
                <flux:subheading class="md:col-span-2 lg:col-span-3">
                    {{ __('Avaliação complementar, veículo e acidente') }}</flux:subheading>

                <flux:select wire:model="fall_height" :label="__('Queda de altura')">
                    <flux:select.option value="">{{ __('—') }}</flux:select.option>
                    <flux:select.option value="1">{{ __('Sim') }}</flux:select.option>
                    <flux:select.option value="0">{{ __('Não') }}</flux:select.option>
                </flux:select>
                <flux:input wire:model="fall_height_meters" type="number" step="0.01"
                    :label="__('Altura da queda (m)')" />
                <flux:select wire:model="halito_etilico" :label="__('Hálito etílico')">
                    <flux:select.option value="">{{ __('—') }}</flux:select.option>
                    <flux:select.option value="1">{{ __('Sim') }}</flux:select.option>
                    <flux:select.option value="0">{{ __('Não') }}</flux:select.option>
                </flux:select>
                <flux:select wire:model="burn" :label="__('Queimadura')">
                    <flux:select.option value="">{{ __('—') }}</flux:select.option>
                    <flux:select.option value="1">{{ __('Sim') }}</flux:select.option>
                    <flux:select.option value="0">{{ __('Não') }}</flux:select.option>
                </flux:select>
                <flux:input wire:model="burn_percentage" type="number" min="0" max="100"
                    :label="__('Queimadura (%)')" />
                <flux:select wire:model="vehicle_role" :label="__('Veículo ocupava')">
                    <flux:select.option value="">{{ __('—') }}</flux:select.option>
                    <flux:select.option value="automovel">{{ __('Automóvel') }}</flux:select.option>
                    <flux:select.option value="motocicleta">{{ __('Motocicleta') }}</flux:select.option>
                    <flux:select.option value="caminhao">{{ __('Caminhão') }}</flux:select.option>
                    <flux:select.option value="bicicleta">{{ __('Bicicleta') }}</flux:select.option>
                    <flux:select.option value="tracao_animal">{{ __('Tração animal') }}</flux:select.option>
                    <flux:select.option value="a_pe">{{ __('A pé') }}</flux:select.option>
                </flux:select>
                <flux:select wire:model="accident_type" :label="__('Tipo de acidente')">
                    <flux:select.option value="">{{ __('—') }}</flux:select.option>
                    <flux:select.option value="capotamento">{{ __('Capotamento') }}</flux:select.option>
                    <flux:select.option value="tombamento">{{ __('Tombamento') }}</flux:select.option>
                    <flux:select.option value="colisao_frontal">{{ __('Colisão frontal') }}</flux:select.option>
                    <flux:select.option value="colisao_lateral">{{ __('Colisão lateral') }}</flux:select.option>
                    <flux:select.option value="colisao_traseira">{{ __('Colisão traseira') }}</flux:select.option>
                    <flux:select.option value="choque">{{ __('Choque') }}</flux:select.option>
                    <flux:select.option value="atropelamento">{{ __('Atropelamento') }}</flux:select.option>
                    <flux:select.option value="queda">{{ __('Queda') }}</flux:select.option>
                </flux:select>
            </flux:card>
        @endif

        @if ($situacao === '1')
            <flux:card class="grid gap-4 md:grid-cols-2 lg:grid-cols-4">
                <flux:subheading class="md:col-span-2 lg:col-span-4">{{ __('Pupilas') }}</flux:subheading>
                <flux:select wire:model.live="pupil_light_reaction" :label="__('Reação à luz')">
                    <flux:select.option value="">{{ __('—') }}</flux:select.option>
                    <flux:select.option value="present">{{ __('Presente') }}</flux:select.option>
                    <flux:select.option value="absent">{{ __('Ausente') }}</flux:select.option>
                </flux:select>
                @if ($pupil_light_reaction === 'absent')
                    <flux:select wire:model.live="pupil_symmetry" :label="__('Simetria')">
                        <flux:select.option value="">{{ __('—') }}</flux:select.option>
                        <flux:select.option value="isocoric">{{ __('Isocóricas') }}</flux:select.option>
                        <flux:select.option value="anisocoric">{{ __('Anisocóricas') }}</flux:select.option>
                    </flux:select>
                    <flux:select wire:model="pupil_size" :label="__('Tamanho')">
                        <flux:select.option value="">{{ __('—') }}</flux:select.option>
                        <flux:select.option value="miotic">{{ __('Miótica') }}</flux:select.option>
                        <flux:select.option value="mydriatic">{{ __('Midriática') }}</flux:select.option>
                    </flux:select>
                    @if ($pupil_symmetry === 'anisocoric')
                        <flux:select wire:model="pupil_side" :label="__('Lado')">
                            <flux:select.option value="">{{ __('—') }}</flux:select.option>
                            <flux:select.option value="right">{{ __('Direito') }}</flux:select.option>
                            <flux:select.option value="left">{{ __('Esquerdo') }}</flux:select.option>
                        </flux:select>
                    @endif
                @endif
                <flux:textarea wire:model="pupil_notes" :label="__('Observações de pupilas')" rows="2"
                    class="md:col-span-2 lg:col-span-4" />
            </flux:card>
        @endif

        @if ($situacao === '3')
            <flux:card class="grid gap-4 md:grid-cols-2">
                <flux:subheading class="md:col-span-2">{{ __('Recusa de atendimento') }}</flux:subheading>
                <flux:input wire:model="witness_name" :label="__('Testemunha')" />
                <flux:input wire:model="witness_rg" :label="__('RG testemunha')" />
                <flux:input wire:model="witness_ssp" :label="__('SSP testemunha')" />
            </flux:card>
        @endif

        @if ($situacao === '2')
            <flux:card class="grid gap-4 md:grid-cols-2">
                <flux:subheading class="md:col-span-2">{{ __('Óbito') }}</flux:subheading>
                <flux:select wire:model="care_local_id" :label="__('Óbito — onde')"
                    placeholder="{{ __('Selecione o local da cena') }}">
                    <flux:select.option value="">{{ __('—') }}</flux:select.option>
                    @foreach ($careLocals as $cl)
                        <flux:select.option value="{{ $cl->id }}">{{ $cl->name }}</flux:select.option>
                    @endforeach
                </flux:select>
                <flux:input wire:model="death_where" :label="__('Complemento do local')" />
                <flux:textarea wire:model="death_notes" :label="__('Óbito — parecer / notas')" rows="3"
                    class="md:col-span-2" />
            </flux:card>
        @endif

        @if ($situacao === '1')
            <flux:card class="space-y-4">
                <flux:subheading>{{ __('Procedimentos') }}</flux:subheading>
                <div class="grid gap-2 sm:grid-cols-2 lg:grid-cols-3">
                    @forelse ($procedures as $p)
                        <flux:checkbox wire:model="procedure_ids" value="{{ $p->id }}"
                            :label="$p->name" />
                    @empty
                        <flux:text size="sm">{{ __('Cadastre procedimentos em Parâmetros.') }}</flux:text>
                    @endforelse
                </div>
            </flux:card>
        @endif

        @if ($situacao === '1')
            <flux:card class="space-y-4">
                <flux:subheading>{{ __('Equipamentos / acessórios') }}</flux:subheading>
                <div class="grid gap-2 sm:grid-cols-2 lg:grid-cols-3">
                    @forelse ($accessories as $a)
                        <flux:checkbox wire:model="accessory_ids" value="{{ $a->id }}"
                            :label="$a->name" />
                    @empty
                        <flux:text size="sm">{{ __('Cadastre acessórios em Parâmetros.') }}</flux:text>
                    @endforelse
                </div>
            </flux:card>
        @endif

        @if ($situacao === '1')
            <flux:card class="space-y-4">
                <flux:subheading>{{ __('Matriz de ferimentos') }}</flux:subheading>
                <flux:text size="sm" class="text-zinc-500">
                    {{ __('Clique na região do corpo no diagrama e informe cada tipo de ferimento pela lista ao lado. Os ferimentos escolhidos aparecem abaixo com região e tipo; na edição, remova pelo chip (×).') }}
                </flux:text>

                <div class="grid gap-4 xl:grid-cols-[minmax(0,1fr)_minmax(20rem,24rem)]">
                    <div class="grid gap-4 md:grid-cols-2">
                        @foreach ($injuryMatrix['diagrams'] as $diagram)
                            <div
                                class="rounded-xl border border-zinc-200 bg-zinc-50/60 p-4 dark:border-zinc-700 dark:bg-zinc-900/30">
                                <flux:text class="mb-3 text-center font-medium">{{ $diagram['label'] }}</flux:text>
                                <svg viewBox="0 0 160 260" role="img"
                                    aria-label="{{ __('Imagem corporal :view', ['view' => $diagram['label']]) }}"
                                    class="mx-auto h-80 max-h-[22rem] w-full max-w-56">
                                    <rect x="0" y="0" width="160" height="260" rx="24"
                                        class="fill-white dark:fill-zinc-950" />
                                    @foreach ($diagram['areas'] as $area)
                                        @php
                                            $areaClass = $area['selected']
                                                ? 'fill-blue-500 stroke-blue-700 dark:fill-cyan-500 dark:stroke-cyan-300'
                                                : ($area['enabled']
                                                    ? 'fill-zinc-300 stroke-zinc-500 hover:fill-blue-300 dark:fill-zinc-700 dark:stroke-zinc-500 dark:hover:fill-cyan-700'
                                                    : 'fill-zinc-100 stroke-zinc-200 dark:fill-zinc-900 dark:stroke-zinc-800');
                                        @endphp
                                        <g wire:click="selectInjuryRegion('{{ $area['region'] }}')" role="button"
                                            tabindex="0"
                                            aria-label="{{ __('Selecionar :region', ['region' => $area['region']]) }}"
                                            class="{{ $area['enabled'] ? 'cursor-pointer' : 'cursor-not-allowed opacity-60' }}">
                                            @if ($area['shape'] === 'circle')
                                                <circle cx="{{ $area['x'] }}" cy="{{ $area['y'] }}"
                                                    r="{{ $area['r'] }}"
                                                    class="{{ $areaClass }} stroke-2 transition" />
                                            @else
                                                <rect x="{{ $area['x'] }}" y="{{ $area['y'] }}"
                                                    width="{{ $area['width'] }}" height="{{ $area['height'] }}"
                                                    rx="{{ $area['rx'] }}"
                                                    class="{{ $areaClass }} stroke-2 transition" />
                                            @endif
                                            @if ($area['selected_count'] > 0)
                                                <circle cx="{{ ($area['x'] ?? 80) + 4 }}"
                                                    cy="{{ ($area['y'] ?? 28) + 4 }}" r="7"
                                                    class="fill-red-500 stroke-white stroke-2" />
                                                <text x="{{ ($area['x'] ?? 80) + 4 }}"
                                                    y="{{ ($area['y'] ?? 28) + 7 }}" text-anchor="middle"
                                                    class="fill-white text-[9px] font-bold">{{ $area['selected_count'] }}</text>
                                            @endif
                                        </g>
                                    @endforeach
                                </svg>
                            </div>
                        @endforeach
                    </div>

                    <div class="space-y-4 rounded-xl border border-zinc-200 p-4 dark:border-zinc-700">
                        <div>
                            <flux:text size="sm" class="text-zinc-500">{{ __('Região selecionada') }}
                            </flux:text>
                            <flux:heading size="lg">{{ $injuryMatrix['selected_region'] }}</flux:heading>
                        </div>

                        <div>
                            <flux:text size="sm" class="mb-2 font-medium text-zinc-600 dark:text-zinc-300">
                                {{ __('Tipos de ferimento nesta região') }}</flux:text>
                            <flux:text size="sm" class="mb-2 text-zinc-500">{{ __('Toque para informar ou remover o tipo na região atual.') }}
                            </flux:text>
                        </div>

                        <div class="grid gap-2">
                            @foreach ($injuryMatrix['selected_cells'] as $cell)
                                @if ($cell['site'])
                                    <button type="button" wire:click="toggleInjurySite({{ $cell['site']->id }})"
                                        class="flex items-center justify-between rounded-lg border px-3 py-2 text-start text-sm transition {{ $cell['selected'] ? 'border-red-300 bg-red-50 text-red-800 dark:border-red-700 dark:bg-red-950/40 dark:text-red-200' : 'border-zinc-200 hover:border-blue-300 hover:bg-blue-50 dark:border-zinc-700 dark:hover:border-blue-700 dark:hover:bg-blue-950/30' }}">
                                        <span>{{ $cell['type'] }}</span>
                                        <span
                                            class="text-xs font-medium">{{ $cell['selected'] ? __('Informado') : __('Informar') }}</span>
                                    </button>
                                @else
                                    <button type="button" disabled
                                        class="flex items-center justify-between rounded-lg border border-dashed border-zinc-200 px-3 py-2 text-start text-sm text-zinc-400 dark:border-zinc-800 dark:text-zinc-600">
                                        <span>{{ $cell['type'] }}</span>
                                        <span class="text-xs">{{ __('Não cadastrado') }}</span>
                                    </button>
                                @endif
                            @endforeach
                        </div>

                        @if (count($injuryMatrix['sidebar_extra_rows']) > 0)
                            <div class="border-t border-zinc-200 pt-4 dark:border-zinc-700">
                                <flux:text size="sm" class="mb-2 font-medium text-zinc-600 dark:text-zinc-300">
                                    {{ __('Outros cadastrados nesta região') }}</flux:text>
                                <flux:text size="sm" class="mb-3 text-zinc-500">
                                    {{ __('Locais vindos dos parâmetros cujo nome se refere a esta área corporal (fora dos tipos padrão da matriz).') }}
                                </flux:text>
                                <div class="grid gap-2">
                                    @foreach ($injuryMatrix['sidebar_extra_rows'] as $extraRow)
                                        <button type="button"
                                            wire:click="toggleInjurySite({{ $extraRow['site']->id }})"
                                            class="flex items-center justify-between gap-2 rounded-lg border px-3 py-2 text-start text-sm transition {{ $extraRow['selected'] ? 'border-red-300 bg-red-50 text-red-800 dark:border-red-700 dark:bg-red-950/40 dark:text-red-200' : 'border-zinc-200 hover:border-blue-300 hover:bg-blue-50 dark:border-zinc-700 dark:hover:border-blue-700 dark:hover:bg-blue-950/30' }}">
                                            <span class="min-w-0 flex-1 truncate"
                                                title="{{ $extraRow['site']->name }}">{{ $extraRow['site']->name }}</span>
                                            <span
                                                class="shrink-0 text-xs font-medium">{{ $extraRow['selected'] ? __('Informado') : __('Informar') }}</span>
                                        </button>
                                    @endforeach
                                </div>
                            </div>
                        @endif
                    </div>
                </div>

                @if (count($injuryMatrix['selected_injuries']) > 0)
                    <div
                        class="space-y-2 rounded-xl border border-red-200 bg-red-50 p-4 dark:border-red-900/60 dark:bg-red-950/20">
                        <flux:text size="sm" class="font-medium text-red-900 dark:text-red-100">
                            {{ __('Ferimentos informados') }}</flux:text>
                        <flux:text size="sm" class="text-red-800/90 dark:text-red-200/90">
                            {{ __('Região e tipo conforme a matriz corporal; clique no chip para remover.') }}
                        </flux:text>
                        <div class="flex flex-wrap gap-2">
                            @foreach ($injuryMatrix['selected_injuries'] as $injuryRow)
                                <button type="button"
                                    wire:click="toggleInjurySite({{ $injuryRow['site']->id }})"
                                    class="rounded-full bg-white px-3 py-1 text-start text-sm text-red-800 shadow-sm ring-1 ring-red-200 hover:bg-red-100 dark:bg-red-950 dark:text-red-100 dark:ring-red-800"
                                    title="{{ __('Remover deste registro') }}">
                                    <span class="font-medium">{{ $injuryRow['label'] }}</span>
                                    <span class="ms-1 tabular-nums text-red-600 dark:text-red-300"
                                        aria-hidden="true">×</span>
                                </button>
                            @endforeach
                        </div>
                    </div>
                @endif

                @if (!$injuryMatrix['has_any_registered_matrix_site'])
                    <flux:callout variant="warning">
                        <div class="flex flex-wrap items-center justify-between gap-3">
                            <span>{{ __('Cadastre locais de ferimento em Parâmetros.') }}</span>
                            @if (auth()->user()?->isOperationalCentral())
                                <flux:button size="sm" variant="ghost"
                                    :href="route('operations.parameters.injury-sites')" wire:navigate>
                                    {{ __('Abrir parâmetros') }}
                                </flux:button>
                            @endif
                        </div>
                    </flux:callout>
                @endif
            </flux:card>
        @endif



        @if ($situacao === '1')
            <flux:card class="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
                <flux:subheading class="md:col-span-2 lg:col-span-3">{{ __('Transporte e unidade de saúde') }}
                </flux:subheading>
                <flux:select wire:model="transporte" :label="__('Transporte')">
                    <flux:select.option value="">{{ __('—') }}</flux:select.option>
                    <flux:select.option value="UR">{{ __('UR') }}</flux:select.option>
                    <flux:select.option value="SAME">{{ __('SAME') }}</flux:select.option>
                    <flux:select.option value="OUTROS">{{ __('Outros') }}</flux:select.option>
                </flux:select>
                <flux:select wire:model="health_unit_id" :label="__('Unidade de saúde')"
                    placeholder="{{ __('Opcional') }}">
                    <flux:select.option value="">{{ __('—') }}</flux:select.option>
                    @foreach ($healthUnits as $unit)
                        <flux:select.option value="{{ $unit->id }}">{{ $unit->name }}</flux:select.option>
                    @endforeach
                </flux:select>
                <flux:input wire:model="unidade_saude" :label="__('Unidade de saúde (texto livre)')" />
                <flux:input wire:model="medico_us" :label="__('Médico na US')" />
                <flux:input wire:model="crm_medico_us" :label="__('CRM médico US')" />
            </flux:card>
        @endif

        <flux:card>
            <flux:textarea wire:model="dados_complementares" :label="__('Dados complementares')" rows="4" />
        </flux:card>

        <div class="flex flex-wrap gap-2">
            <flux:button type="submit" variant="primary" wire:loading.attr="disabled">{{ __('Salvar') }}
            </flux:button>
            <flux:button variant="ghost" type="button" :href="route('operations.incidents.show', $incident)"
                wire:navigate>{{ __('Cancelar') }}</flux:button>
        </div>
    </form>
</div>
