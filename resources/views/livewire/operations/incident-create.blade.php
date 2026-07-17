<div class="cco-page-gap">
    @unless ($embeddedInModal)
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <flux:heading size="xl">{{ __('Nova ocorrência') }}</flux:heading>
                <flux:text class="mt-1">{{ __('Triagem com tipo de chamada e localização (OpenStreetMap).') }}</flux:text>
            </div>
            @if (! $guest_intake)
                <div class="flex flex-wrap gap-2">
                    <flux:button variant="ghost" icon="rectangle-stack" :href="route('operations.incidents.index')" wire:navigate>{{ __('Lista') }}</flux:button>
                    <flux:button variant="ghost" icon="radio" :href="route('operations.dispatch')" wire:navigate>{{ __('CCO') }}</flux:button>
                </div>
            @endif
        </div>

        @unless ($guest_intake)
            <flux:callout variant="info">
                {{ __('O cadastro não vincula a ocorrência a uma base (município). A hora da chamada é a mesma da data/hora da ocorrência.') }}
            </flux:callout>
        @endunless
    @endunless

    @if ($errors->any())
        <flux:callout variant="danger">
            <ul class="mt-1 list-inside list-disc space-y-1 text-sm">
                @foreach ($errors->all() as $message)
                    <li>{{ $message }}</li>
                @endforeach
            </ul>
        </flux:callout>
    @endif

    <flux:card>
        <div class="grid gap-6">

            {{-- Dados da chamada --}}
            <div class="grid gap-4 lg:grid-cols-2">
                @if (! $embeddedInModal && ! $guest_intake)
                    <flux:input wire:model="occurred_at" type="datetime-local" :label="__('Data e hora da ocorrência / chamada')" class="lg:col-span-2" />
                @endif

                <flux:input
                    wire:model="caller_phone"
                    type="tel"
                    autocomplete="tel"
                    :label="__('Telefone')"
                />
                <flux:input wire:model="caller_name" :label="__('Solicitante')" />

                <flux:textarea wire:model="description" :label="__('Descrição')" rows="4" class="lg:col-span-2" />
            </div>

            {{-- Endereço --}}
            <div class="border-t border-slate-200/90 pt-6 dark:border-slate-700/60">
                <div class="mb-4 flex flex-col gap-3 sm:flex-row sm:items-end">
                    <div class="min-w-0 flex-1">
                        <flux:input
                            wire:model="addressGeocodeQuery"
                            :label="__('Buscar endereço')"
                            placeholder="{{ __('Ex.: Asa Sul, Brasília — DF') }}"
                            @keydown.enter.prevent="$wire.geocodeAddressSearch()"
                        />
                    </div>
                    <flux:button type="button" variant="ghost" wire:click="geocodeAddressSearch" wire:loading.attr="disabled">
                        {{ __('Buscar no mapa') }}
                    </flux:button>
                </div>
                @error('addressGeocodeQuery')
                    <flux:callout variant="danger" class="mb-4">{{ $message }}</flux:callout>
                @enderror

                <div class="grid gap-4 lg:grid-cols-2">
                    <flux:input wire:model="address_line" :label="__('Logradouro / endereço')" />
                    <flux:input wire:model="number" :label="__('Número')" />
                    <flux:input wire:model="district" :label="__('Bairro')" />
                    <flux:input wire:model="city" :label="__('Cidade')" />
                    <flux:textarea wire:model="reference_notes" :label="__('Referência')" rows="2" class="lg:col-span-2" />

                    <div class="incident-osm-host lg:col-span-2">
                        <flux:text size="sm" class="mb-2 font-medium">{{ __('Mapa') }}</flux:text>
                        <div
                            wire:ignore
                            class="incident-osm-map h-64 overflow-hidden rounded-xl border border-slate-200/95 bg-slate-50 dark:border-slate-700/70 dark:bg-slate-900/40"
                            x-data="incidentOsmMap()"
                            x-init="init()"
                        >
                            <div x-ref="mapEl" class="h-full w-full"></div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Natureza e dados da vítima --}}
            <div class="border-t border-slate-200/90 pt-6 dark:border-slate-700/60">
                <div class="grid gap-4 lg:grid-cols-2">
                    <flux:select wire:model.live="nature_id" :label="__('Natureza')" placeholder="{{ __('Selecione') }}" class="lg:col-span-2">
                        @foreach ($natures as $n)
                            <flux:select.option value="{{ $n->id }}">{{ $n->name }}</flux:select.option>
                        @endforeach
                    </flux:select>

                    <flux:input wire:model="patient_name" :label="__('Nome do paciente')" />
                    <flux:input wire:model.number="patient_age" type="number" :label="__('Idade')" />

                    <flux:select wire:model="patient_sex" :label="__('Sexo')" placeholder="{{ __('Opcional') }}" class="lg:col-span-2">
                        <flux:select.option value="">{{ __('—') }}</flux:select.option>
                        <flux:select.option value="M">{{ __('Masculino') }}</flux:select.option>
                        <flux:select.option value="F">{{ __('Feminino') }}</flux:select.option>
                        <flux:select.option value="O">{{ __('Outro') }}</flux:select.option>
                    </flux:select>
                </div>
            </div>

            {{-- Manchester --}}
            <div class="border-t border-slate-200/90 pt-6 dark:border-slate-700/60">
                <div class="grid gap-4 lg:grid-cols-2">
                    <flux:select
                        wire:model="manchester_risk"
                        :label="__('Classificação de risco (Manchester)')"
                        placeholder="{{ __('Opcional') }}"
                        class="lg:col-span-2"
                    >
                        <flux:select.option value="">{{ __('Não definido') }}</flux:select.option>
                        <flux:select.option value="red">{{ __('Vermelho') }} — {{ __('Imediato') }}</flux:select.option>
                        <flux:select.option value="orange">{{ __('Laranja') }} — {{ __('Muito urgente') }}</flux:select.option>
                        <flux:select.option value="yellow">{{ __('Amarelo') }} — {{ __('Urgente') }}</flux:select.option>
                        <flux:select.option value="green">{{ __('Verde') }} — {{ __('Pouco urgente') }}</flux:select.option>
                        <flux:select.option value="blue">{{ __('Azul') }} — {{ __('Não urgente') }}</flux:select.option>
                    </flux:select>

                    <flux:text size="sm" class="text-zinc-500 lg:col-span-2">
                        {{ __('Use Manchester para indicar a prioridade clínica (independente do status operacional e do tipo de chamada).') }}
                    </flux:text>
                </div>
            </div>

            {{-- Botões de tipo de chamada (submit) --}}
            <div class="border-t border-slate-200/95 pt-6 dark:border-slate-700/60">
                <flux:text size="sm" class="mb-3 text-zinc-500">
                    {{ __('Clique no tipo para registrar. Não completada, Trote e Administrativo exigem apenas o telefone.') }}
                </flux:text>
                <div class="flex flex-wrap justify-end gap-2">
                    @foreach ($callTypesForButtons as $type)
                        @php
                            $btnClass = match ($type->value) {
                                'C' => 'bg-amber-500   hover:bg-amber-600   focus-visible:ring-amber-400',
                                'T' => 'bg-orange-500  hover:bg-orange-600  focus-visible:ring-orange-400',
                                'A' => 'bg-sky-500     hover:bg-sky-600     focus-visible:ring-sky-400',
                                'L' => 'bg-violet-500  hover:bg-violet-600  focus-visible:ring-violet-400',
                                'N' => 'bg-emerald-600 hover:bg-emerald-700 focus-visible:ring-emerald-500',
                                'U' => 'bg-red-600     hover:bg-red-700     focus-visible:ring-red-500',
                                default => 'bg-zinc-600 hover:bg-zinc-700',
                            };
                        @endphp
                        <button
                            type="button"
                            wire:click="saveWithCallType('{{ $type->value }}')"
                            wire:loading.attr="disabled"
                            wire:target="saveWithCallType"
                            class="inline-flex min-h-10 items-center justify-center rounded-lg px-4 py-2 text-sm font-semibold text-white shadow-sm transition focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-60 dark:focus-visible:ring-offset-zinc-900 {{ $btnClass }}"
                        >
                            {{ $type->label() }}
                        </button>
                    @endforeach
                </div>
            </div>

        </div>
    </flux:card>

    @include('livewire.operations.partials.incident-create-duplicate-modal')
</div>
