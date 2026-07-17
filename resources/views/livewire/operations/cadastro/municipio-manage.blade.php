<div class="cco-page-gap">
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <flux:heading size="xl">{{ __('Bases') }}</flux:heading>
            <flux:text class="mt-1">{{ __('Cadastro de bases operacionais (`municipios`). Efetivo e viaturas usam o `municipio_id` da base escolhida.') }}</flux:text>
        </div>
        <flux:button variant="ghost" icon="radio" :href="route('operations.dispatch')" wire:navigate>{{ __('CCO') }}</flux:button>
    </div>

    @error('delete')
        <flux:callout variant="danger">{{ $message }}</flux:callout>
    @enderror

    @if ($message)
        <flux:callout variant="success">{{ $message }}</flux:callout>
    @endif

    @can('create', App\Models\Municipio::class)
        <flux:card class="space-y-4">
            <flux:subheading>{{ $editingId ? __('Editar base') : __('Nova base') }}</flux:subheading>
            <form wire:submit="save"
                  x-data="municipioForm()"
                  class="grid gap-4 md:grid-cols-2 lg:grid-cols-3">

                {{-- Razão social com verificação de nome duplicado --}}
                <div class="md:col-span-2 lg:col-span-3">
                    <div class="relative">
                        <flux:input
                            wire:model="razao_social"
                            wire:blur="checkDuplicateRazaoSocial"
                            :label="__('Razão social')"
                        />
                        <div wire:loading wire:target="checkDuplicateRazaoSocial"
                             class="absolute right-3 top-8 flex items-center">
                            <svg class="h-4 w-4 animate-spin text-zinc-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path>
                            </svg>
                        </div>
                    </div>
                    @if ($razaoSocialTaken)
                        <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ __('Este nome já está cadastrado') }}</p>
                    @endif
                    @error('razao_social')
                        <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                    @enderror
                </div>

                {{-- CNPJ com máscara e validação --}}
                <div>
                    <flux:input
                        wire:model.blur="cnpj"
                        :label="__('CNPJ')"
                        maxlength="18"
                        x-on:input="maskCnpj($event)"
                        x-on:blur="validateCnpjBlur($event)"
                        placeholder="XX.XXX.XXX/0001-XX"
                    />
                    <p x-show="cnpjError" x-text="cnpjError" class="mt-1 text-sm text-red-600 dark:text-red-400"></p>
                    @error('cnpj')
                        <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                    @enderror
                </div>

                <flux:input wire:model="ie" :label="__('IE')" />

                {{-- Telefone com máscara --}}
                <div>
                    <flux:input
                        wire:model.blur="phone"
                        :label="__('Telefone')"
                        maxlength="15"
                        x-on:input="maskPhone($event)"
                        x-on:blur="validatePhone($event.target.value)"
                        placeholder="(XX) XXXXX-XXXX"
                    />
                    <p x-show="phoneError" x-text="phoneError" class="mt-1 text-sm text-red-600 dark:text-red-400"></p>
                    @error('phone')
                        <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                    @enderror
                </div>

                {{-- CEP com ViaCEP --}}
                <div>
                    <div class="relative">
                        <flux:input
                            wire:model.blur="zipcode"
                            :label="__('CEP')"
                            maxlength="9"
                            x-on:input="maskCep($event)"
                            x-on:blur="fetchCep($event)"
                            placeholder="XXXXX-XXX"
                        />
                        <div x-show="cepLoading"
                             class="absolute right-3 top-8 flex items-center">
                            <svg class="h-4 w-4 animate-spin text-zinc-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path>
                            </svg>
                        </div>
                    </div>
                    <p x-show="cepError" x-text="cepError" class="mt-1 text-sm text-red-600 dark:text-red-400"></p>
                    @error('zipcode')
                        <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                    @enderror
                </div>

                <flux:input wire:model="address" :label="__('Endereço')" class="md:col-span-2" />
                <flux:input wire:model="number" :label="__('Número')" />
                <flux:input wire:model="district" :label="__('Bairro')" />
                <flux:input wire:model="city" :label="__('Cidade')" />
                <flux:input wire:model="state" :label="__('UF')" />

                {{-- Contato pré-despacho --}}
                <div class="md:col-span-2 lg:col-span-3">
                    <flux:subheading class="mb-2">{{ __('Contatos pré-despacho') }}</flux:subheading>
                    <flux:text size="sm" class="mb-3 text-slate-500 dark:text-slate-400">
                        {{ __('Configure os números para contato antes do empenho de viaturas. Estes valores serão preenchidos automaticamente no modal de despacho.') }}
                    </flux:text>
                </div>

                <flux:input wire:model="dispatchContactRamal" :label="__('Ramal')" placeholder="{{ __('Ex: 192') }}" />
                <flux:input wire:model="dispatchContactPhone" :label="__('Telefone')" placeholder="{{ __('Ex: (11) 99999-0000') }}" />
                <flux:input wire:model="dispatchContactWhatsapp" :label="__('WhatsApp')" placeholder="{{ __('Ex: wa.me/5511999990000') }}" />

                <flux:switch wire:model="active" :label="__('Ativa')" align="left" class="md:col-span-2 lg:col-span-3" />
                <div class="flex flex-wrap gap-2 md:col-span-2 lg:col-span-3">
                    <flux:button type="submit" variant="primary">{{ $editingId ? __('Salvar') : __('Incluir') }}</flux:button>
                    @if ($editingId)
                        <flux:button type="button" variant="ghost" wire:click="resetForm">{{ __('Cancelar') }}</flux:button>
                    @endif
                </div>
            </form>
        </flux:card>
    @endcan

    <flux:card class="space-y-4">
        <flux:subheading>{{ __('Bases cadastradas') }}</flux:subheading>
        <div class="overflow-x-auto rounded-xl border border-zinc-200 dark:border-zinc-700">
            <table class="min-w-full divide-y divide-zinc-200 text-start text-sm dark:divide-zinc-700">
                <thead class="bg-zinc-50 dark:bg-zinc-900/50">
                    <tr>
                        <th class="px-4 py-3 font-medium">{{ __('Razão social') }}</th>
                        <th class="px-4 py-3 font-medium">{{ __('Cidade / UF') }}</th>
                        <th class="px-4 py-3 font-medium">{{ __('CNPJ') }}</th>
                        <th class="px-4 py-3 font-medium">{{ __('Ativa') }}</th>
                        <th class="px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-100 bg-white dark:divide-zinc-800 dark:bg-zinc-900">
                    @forelse ($bases as $b)
                        <tr wire:key="base-{{ $b->id }}">
                            <td class="px-4 py-3 font-medium">{{ $b->razao_social }}</td>

                            {{-- Cidade / UF com badge de georref --}}
                            <td class="px-4 py-3 text-zinc-600 dark:text-zinc-300">
                                <span>{{ $b->city ?? '—' }}{{ $b->state ? ' / '.$b->state : '' }}</span>
                                @if ($b->ibgeMunicipio)
                                    <span class="ml-1.5 inline-flex items-center gap-0.5 rounded-full bg-emerald-50 px-1.5 py-0.5 text-[10px] font-semibold text-emerald-700 ring-1 ring-emerald-200 dark:bg-emerald-900/20 dark:text-emerald-400 dark:ring-emerald-800">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-2.5 w-2.5" viewBox="0 0 20 20" fill="currentColor">
                                            <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd" />
                                        </svg>
                                        IBGE
                                    </span>
                                @endif
                            </td>

                            <td class="px-4 py-3 font-mono text-xs">{{ $b->cnpj ?? '—' }}</td>
                            <td class="px-4 py-3">{{ $b->active ? __('Sim') : __('Não') }}</td>

                            <td class="px-4 py-3 text-end">
                                <div class="flex items-center justify-end gap-1">

                                    {{-- Botão "Ver no mapa IBGE" --}}
                                    @if ($b->ibgeMunicipio)
                                        <button
                                            type="button"
                                            title="{{ __('Ver no mapa IBGE') }}"
                                            class="rounded-lg p-1.5 text-zinc-400 transition hover:bg-blue-50 hover:text-blue-600 dark:hover:bg-blue-900/20 dark:hover:text-blue-400"
                                            @click="$dispatch('municipio-ibge-map-open', @js([
                                                'lat'        => $b->ibgeMunicipio->latitude,
                                                'lng'        => $b->ibgeMunicipio->longitude,
                                                'nome'       => $b->ibgeMunicipio->nome,
                                                'uf'         => $b->ibgeMunicipio->uf,
                                                'codigo'     => $b->ibgeMunicipio->codigo_ibge,
                                                'area'       => $b->ibgeMunicipio->area_km2,
                                                'geojsonUrl' => route('operations.cadastro.ibge.geojson', $b->ibgeMunicipio->id),
                                            ]))"
                                        >
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                                <path d="M21 10c0 7-9 13-9 13S3 17 3 10a9 9 0 1 1 18 0z"/>
                                                <circle cx="12" cy="10" r="3"/>
                                            </svg>
                                        </button>
                                    @endif

                                    @can('update', $b)
                                        <x-crud-icon-edit :item-id="$b->id" />
                                    @endcan
                                    @can('delete', $b)
                                        <x-crud-icon-delete :item-id="$b->id" :confirm-message="__('Excluir esta base?')" />
                                    @endcan
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="px-4 py-6 text-zinc-500">{{ __('Nenhuma base cadastrada.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </flux:card>

    {{-- ── Modal georref IBGE ─────────────────────────────────────────────── --}}
    {{-- wire:ignore: Livewire não toca neste subtree; Alpine + Leaflet gerenciam --}}
    <div wire:ignore>
        <div x-data="municipioIbgeModal()">

            {{-- Overlay --}}
            <div
                x-show="open"
                x-transition:enter="transition ease-out duration-200"
                x-transition:enter-start="opacity-0"
                x-transition:enter-end="opacity-100"
                x-transition:leave="transition ease-in duration-150"
                x-transition:leave-start="opacity-100"
                x-transition:leave-end="opacity-0"
                @keydown.escape.window="close()"
                class="fixed inset-0 z-50 flex items-end justify-center p-4 sm:items-center"
                style="display:none"
            >
                {{-- Backdrop --}}
                <div
                    class="absolute inset-0 bg-black/60 backdrop-blur-sm"
                    @click="close()"
                ></div>

                {{-- Card --}}
                <div
                    x-show="open"
                    x-transition:enter="transition ease-out duration-200"
                    x-transition:enter-start="opacity-0 scale-95 translate-y-4 sm:translate-y-0"
                    x-transition:enter-end="opacity-100 scale-100 translate-y-0"
                    x-transition:leave="transition ease-in duration-150"
                    x-transition:leave-start="opacity-100 scale-100 translate-y-0"
                    x-transition:leave-end="opacity-0 scale-95 translate-y-4 sm:translate-y-0"
                    class="relative z-10 w-full max-w-2xl overflow-hidden rounded-2xl bg-white shadow-2xl dark:bg-zinc-900"
                >
                    {{-- Cabeçalho --}}
                    <div class="flex items-start justify-between gap-4 border-b border-zinc-200 px-5 py-4 dark:border-zinc-700">
                        <div>
                            <p class="font-semibold text-zinc-900 dark:text-white">
                                <span x-text="(ibge.nome ?? '—') + ' / ' + (ibge.uf ?? '—')"></span>
                            </p>
                            <p class="mt-0.5 text-xs text-zinc-500">
                                {{ __('Código IBGE') }}: <span x-text="ibge.codigo ?? '—'" class="font-mono"></span>
                            </p>
                        </div>
                        <button
                            @click="close()"
                            class="rounded-lg p-1.5 text-zinc-400 transition hover:bg-zinc-100 hover:text-zinc-700 dark:hover:bg-zinc-800 dark:hover:text-zinc-200"
                            :aria-label="'{{ __('Fechar') }}'"
                        >
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd" />
                            </svg>
                        </button>
                    </div>

                    {{-- Grade de dados geográficos --}}
                    <div class="grid grid-cols-2 gap-px border-b border-zinc-200 bg-zinc-200 dark:border-zinc-700 dark:bg-zinc-700 sm:grid-cols-4">
                        <div class="bg-white px-4 py-3 dark:bg-zinc-900">
                            <p class="text-[10px] font-semibold uppercase tracking-wider text-zinc-400">{{ __('Latitude') }}</p>
                            <p class="mt-0.5 font-mono text-sm font-semibold text-zinc-800 dark:text-zinc-100"
                               x-text="ibge.lat ? parseFloat(ibge.lat).toFixed(6) : '—'"></p>
                        </div>
                        <div class="bg-white px-4 py-3 dark:bg-zinc-900">
                            <p class="text-[10px] font-semibold uppercase tracking-wider text-zinc-400">{{ __('Longitude') }}</p>
                            <p class="mt-0.5 font-mono text-sm font-semibold text-zinc-800 dark:text-zinc-100"
                               x-text="ibge.lng ? parseFloat(ibge.lng).toFixed(6) : '—'"></p>
                        </div>
                        <div class="bg-white px-4 py-3 dark:bg-zinc-900">
                            <p class="text-[10px] font-semibold uppercase tracking-wider text-zinc-400">{{ __('Área') }}</p>
                            <p class="mt-0.5 text-sm font-semibold text-zinc-800 dark:text-zinc-100"
                               x-text="ibge.area ? parseFloat(ibge.area).toLocaleString('pt-BR', {maximumFractionDigits:1}) + ' km²' : '—'"></p>
                        </div>
                        <div class="bg-white px-4 py-3 dark:bg-zinc-900">
                            <p class="text-[10px] font-semibold uppercase tracking-wider text-zinc-400">{{ __('Estado') }}</p>
                            <p class="mt-0.5 text-sm font-semibold text-zinc-800 dark:text-zinc-100"
                               x-text="ibge.uf ?? '—'"></p>
                        </div>
                    </div>

                    {{-- Mapa Leaflet --}}
                    <div class="relative">
                        {{-- Spinner de carregamento do polígono --}}
                        <div
                            x-show="loadingPolygon"
                            class="absolute inset-0 z-10 flex items-center justify-center bg-white/70 dark:bg-zinc-900/70"
                        >
                            <svg class="h-6 w-6 animate-spin text-blue-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path>
                            </svg>
                        </div>
                        <div x-ref="mapEl" style="width:100%;height:360px;"></div>
                    </div>
                </div>
            </div>

        </div>
    </div>

</div>
