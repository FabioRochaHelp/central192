<div class="cco-page-gap">
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <flux:heading size="xl">{{ __('Turnos de serviço') }}</flux:heading>
            <flux:text class="mt-1">{{ __('Abertura e gestão de turnos por viatura e efetivo, conforme docs/migracao (turno).') }}</flux:text>
        </div>
        <div class="flex flex-wrap gap-2">
            <flux:button variant="ghost" icon="truck" :href="route('operations.fleet')" wire:navigate>{{ __('Painel turnos/viaturas') }}</flux:button>
            <flux:button variant="ghost" icon="radio" :href="route('operations.dispatch')" wire:navigate>{{ __('CCO') }}</flux:button>
        </div>
    </div>

    @if (auth()->user()?->isOperationalCentral())
        <flux:card class="space-y-3">
            <flux:subheading>{{ __('Base (município)') }}</flux:subheading>
            <flux:text class="text-slate-600 dark:text-slate-400">{{ __('Operadores da central podem cadastrar turnos para qualquer base após selecioná-la. Operadores municipais ficam restritos à própria base.') }}</flux:text>
            <flux:select wire:model.live="selectedOperationalMunicipioId" :label="__('Base')" placeholder="{{ __('Selecione a base') }}">
                <flux:select.option value="">{{ __('—') }}</flux:select.option>
                @foreach ($operationalMunicipios as $municipio)
                    <flux:select.option value="{{ $municipio->id }}">{{ $municipio->razao_social }}</flux:select.option>
                @endforeach
            </flux:select>
        </flux:card>
    @else
        <flux:callout variant="info">{{ __('Turnos serão criados apenas para a sua base (município vinculado ao usuário).') }}</flux:callout>
    @endif

    @if ($scopeMunicipioId === null)
        <flux:callout variant="warning">{{ __('Selecione a base para listar viaturas, efetivo e turnos.') }}</flux:callout>
    @endif

    @error('scope')
        <flux:callout variant="danger">{{ $message }}</flux:callout>
    @enderror

    @error('close')
        <flux:callout variant="danger">{{ $message }}</flux:callout>
    @enderror

    @if ($message)
        <flux:callout variant="success">{{ $message }}</flux:callout>
    @endif

    @can('create', App\Models\Shift::class)
        <flux:card class="space-y-4">
            <flux:subheading>{{ __('Novo turno') }}</flux:subheading>
            @if ($vehicles->isEmpty())
                <flux:callout variant="warning">
                    {{ __('Todas as viaturas desta base já estão em turno ativo. Encerre um turno para liberar.') }}
                </flux:callout>
            @endif
            <form wire:submit="save"
                  x-data="shiftForm()"
                  class="grid gap-4 md:grid-cols-2 lg:grid-cols-3">

                {{-- Viatura --}}
                <div class="md:col-span-2 lg:col-span-3">
                    <flux:select wire:model="vehicle_id" :label="__('Viatura')" placeholder="{{ __('Selecione') }}">
                        <flux:select.option value="">{{ __('—') }}</flux:select.option>
                        @foreach ($vehicles as $v)
                            <flux:select.option value="{{ $v->id }}">{{ $v->prefix ?? __('Sem prefixo') }} · {{ $v->plate ?? __('Sem placa') }}</flux:select.option>
                        @endforeach
                    </flux:select>
                    @error('vehicle_id')
                        <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                    @enderror
                </div>

                {{-- Início: sempre o momento do servidor ao salvar --}}
                <div>
                    <p class="mb-1 block text-sm font-medium text-zinc-700 dark:text-zinc-300">{{ __('Início') }}</p>
                    <div class="flex items-center gap-2 rounded-lg border border-zinc-200 bg-zinc-50 px-3 py-2 text-sm text-zinc-500 dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-400">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 shrink-0 text-zinc-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                        {{ __('Definido automaticamente ao salvar') }}
                    </div>
                </div>

                {{-- Data/hora de fim --}}
                <div>
                    <flux:input
                        wire:model="ends_at"
                        type="datetime-local"
                        :label="__('Fim')"
                        x-on:change="validateEnd($event.target.value)"
                    />
                    <p x-show="endError" x-text="endError" class="mt-1 text-sm text-red-600 dark:text-red-400"></p>
                    @error('ends_at')
                        <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                    @enderror
                </div>

                {{-- Aviso de expediente acima de 24h --}}
                <div class="md:col-span-2 lg:col-span-3" x-show="durationWarning" x-cloak>
                    <p x-text="durationWarning"
                       class="rounded-lg border border-amber-300 bg-amber-50 px-3 py-2 text-sm text-amber-700 dark:border-amber-700 dark:bg-amber-900/20 dark:text-amber-400">
                    </p>
                </div>

                {{-- Estado operacional --}}
                <flux:select wire:model.live="status" :label="__('Estado operacional')">
                    @foreach ($statusCases as $st)
                        <flux:select.option value="{{ $st->value }}">{{ $st->label() }}</flux:select.option>
                    @endforeach
                </flux:select>

                @php
                    $selectedStatus = \App\Domain\Operations\Enums\ShiftStatus::tryFrom($status);
                    $requiresStaff = $selectedStatus?->requiresStaffOnOpen() ?? true;
                @endphp

                {{-- Efetivo: picker pesquisável com cargo --}}
                @if ($requiresStaff)
                <div x-data="{ staffSearch: '' }" class="md:col-span-2 lg:col-span-3">
                    {{-- Cabeçalho --}}
                    <div class="mb-1.5 flex items-center justify-between">
                        <p class="text-sm font-medium text-zinc-700 dark:text-zinc-300">
                            {{ __('Efetivo no turno') }}
                            <span class="ml-0.5 text-red-500" title="{{ __('Obrigatório') }}">*</span>
                        </p>
                        <span class="text-xs text-zinc-400">
                            {{ count($staffIds) }}
                            {{ count($staffIds) === 1 ? __('selecionado') : __('selecionados') }}
                        </span>
                    </div>

                    {{-- Campo de busca --}}
                    <div class="relative mb-1.5">
                        <svg class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-zinc-400"
                             xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-4.35-4.35M17 11A6 6 0 1 1 5 11a6 6 0 0 1 12 0z"/>
                        </svg>
                        <input
                            type="text"
                            x-model="staffSearch"
                            placeholder="{{ __('Buscar por nome...') }}"
                            class="w-full rounded-lg border border-zinc-300 bg-white py-2 pl-9 pr-8 text-sm text-zinc-800 placeholder-zinc-400 focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500 dark:border-zinc-600 dark:bg-zinc-800 dark:text-zinc-200"
                        />
                        <button
                            type="button"
                            x-show="staffSearch"
                            x-on:click="staffSearch = ''"
                            class="absolute right-2.5 top-1/2 -translate-y-1/2 text-zinc-400 hover:text-zinc-600"
                            aria-label="{{ __('Limpar busca') }}">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd" />
                            </svg>
                        </button>
                    </div>

                    {{-- Lista rolável --}}
                    <div class="max-h-56 overflow-y-auto rounded-lg border border-zinc-200 dark:border-zinc-700">
                        @forelse ($staffMembers as $s)
                            <label
                                wire:key="staff-pick-{{ $s->id }}"
                                data-staff-name="{{ strtolower($s->name) }}"
                                x-show="staffSearch === '' || $el.dataset.staffName.includes(staffSearch.toLowerCase())"
                                class="flex cursor-pointer items-center gap-3 border-b border-zinc-100 px-3 py-2.5 last:border-0 transition-colors hover:bg-zinc-50 dark:border-zinc-800 dark:hover:bg-zinc-800/60"
                            >
                                <input
                                    type="checkbox"
                                    wire:model="staffIds"
                                    value="{{ $s->id }}"
                                    class="h-4 w-4 shrink-0 rounded border-zinc-300 text-blue-600 focus:ring-blue-500"
                                />
                                <span class="min-w-0 flex-1 truncate text-sm font-medium text-zinc-800 dark:text-zinc-200">
                                    {{ $s->name }}
                                </span>
                                @if ($s->cargo)
                                    <span class="shrink-0 rounded-full bg-zinc-100 px-2 py-0.5 text-xs font-medium text-zinc-500 dark:bg-zinc-700 dark:text-zinc-400">
                                        {{ $s->cargo->label() }}
                                    </span>
                                @endif
                            </label>
                        @empty
                            <p class="px-3 py-6 text-center text-sm text-zinc-500">
                                {{ __('Nenhum efetivo cadastrado nesta base.') }}
                            </p>
                        @endforelse
                    </div>

                    @error('staffIds')
                        <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                    @enderror
                </div>
                @else
                    <div class="md:col-span-2 lg:col-span-3">
                        <flux:callout variant="info" icon="information-circle">
                            {{ __('Turnos com estado Baixado, Oficina ou Acidente não exigem efetivo vinculado.') }}
                        </flux:callout>
                    </div>
                @endif

                <div class="flex flex-wrap gap-2 md:col-span-2 lg:col-span-3">
                    <flux:button type="submit" variant="primary" :disabled="$vehicles->isEmpty()">
                        {{ __('Abrir turno') }}
                    </flux:button>
                </div>
            </form>
        </flux:card>
    @endcan

    <flux:card class="space-y-4">
        <flux:subheading>{{ __('Turnos cadastrados') }}</flux:subheading>
        <div class="cco-table-shell">
            <table class="min-w-full divide-y divide-slate-200 text-start text-sm dark:divide-slate-800/80">
                <thead>
                    <tr>
                        <th class="px-4 py-3 font-medium">{{ __('ID') }}</th>
                        <th class="px-4 py-3 font-medium">{{ __('Viatura') }}</th>
                        <th class="px-4 py-3 font-medium">{{ __('Estado') }}</th>
                        <th class="px-4 py-3 font-medium">{{ __('Início') }}</th>
                        <th class="px-4 py-3 font-medium">{{ __('Fim') }}</th>
                        <th class="px-4 py-3 font-medium">{{ __('Equipe') }}</th>
                        <th class="px-4 py-3 font-medium">{{ __('Check-list') }}</th>
                        <th class="px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200 dark:divide-slate-800/80">
                    @forelse ($shifts as $shift)
                        @php
                            $isActive    = $shift->starts_at->isPast() && $shift->ends_at->isFuture();
                            $isEmpenhado = $shift->status === \App\Domain\Operations\Enums\ShiftStatus::Empenhado;
                        @endphp
                        <tr wire:key="shift-row-{{ $shift->id }}">
                            <td class="whitespace-nowrap px-4 py-3 font-mono text-xs text-slate-600 dark:text-slate-400">#{{ $shift->id }}</td>
                            <td class="px-4 py-3 font-medium text-slate-800 dark:text-slate-100">
                                {{ $shift->vehicle?->prefix ?? '—' }} · {{ $shift->vehicle?->plate ?? '—' }}
                            </td>
                            <td class="px-4 py-3">{{ $shift->status->label() }}</td>
                            <td class="whitespace-nowrap px-4 py-3 tabular-nums text-slate-600 dark:text-slate-400">{{ $shift->starts_at->format('d/m/Y H:i') }}</td>
                            <td class="whitespace-nowrap px-4 py-3 tabular-nums text-slate-600 dark:text-slate-400">
                                {{ $shift->ends_at->format('d/m/Y H:i') }}
                                @if ($isActive)
                                    <span class="ml-1 inline-block h-2 w-2 rounded-full bg-emerald-400" title="{{ __('Em andamento') }}"></span>
                                @endif
                            </td>
                            <td class="max-w-[14rem] px-4 py-3 text-xs text-slate-600 dark:text-slate-400">
                                {{ $shift->staff->pluck('name')->implode(', ') ?: '—' }}
                            </td>
                            <td class="px-4 py-3">
                                @if ($shift->isChecklistComplete())
                                    <flux:badge size="sm" color="green">{{ __('Concluído') }}</flux:badge>
                                @elseif ($shift->checklist_observation)
                                    <flux:badge size="sm" color="amber" title="{{ $shift->checklist_observation }}">{{ __('Pendente') }}</flux:badge>
                                @elseif ($isActive)
                                    <flux:badge size="sm" color="red">{{ __('Pendente') }}</flux:badge>
                                @else
                                    <span class="text-xs text-slate-400">—</span>
                                @endif
                            </td>
                            <td class="whitespace-nowrap px-4 py-3 text-end">
                                @can('update', $shift)
                                    @if ($isActive)
                                        <flux:button
                                            size="xs"
                                            variant="ghost"
                                            icon="clipboard-document-check"
                                            wire:click="openChecklistModal({{ $shift->id }})"
                                        >
                                            {{ __('Check-list') }}
                                        </flux:button>
                                    @endif
                                @endcan
                                @can('update', $shift)
                                    @if ($isActive)
                                        @if ($isEmpenhado)
                                            <span title="{{ __('Viatura em despacho ativo') }}"
                                                  class="inline-flex cursor-not-allowed items-center gap-1 rounded-lg border border-zinc-200 bg-zinc-50 px-2 py-1 text-xs text-zinc-400 dark:border-zinc-700 dark:bg-zinc-800">
                                                <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636"/>
                                                </svg>
                                                {{ __('Encerrar') }}
                                            </span>
                                        @else
                                            <button
                                                type="button"
                                                wire:click="closeShift({{ $shift->id }})"
                                                wire:confirm="{{ __('Encerrar o turno #:id agora? A viatura e o efetivo serão liberados.', ['id' => $shift->id]) }}"
                                                wire:loading.attr="disabled"
                                                wire:target="closeShift({{ $shift->id }})"
                                                class="inline-flex items-center gap-1 rounded-lg border border-red-200 bg-red-50 px-2 py-1 text-xs font-medium text-red-600 transition hover:border-red-300 hover:bg-red-100 disabled:opacity-50 dark:border-red-800 dark:bg-red-900/20 dark:text-red-400">
                                                <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 10a1 1 0 011-1h4a1 1 0 011 1v4a1 1 0 01-1 1h-4a1 1 0 01-1-1v-4z"/>
                                                </svg>
                                                <span wire:loading.remove wire:target="closeShift({{ $shift->id }})">{{ __('Encerrar') }}</span>
                                                <span wire:loading wire:target="closeShift({{ $shift->id }})">{{ __('Encerrando…') }}</span>
                                            </button>
                                        @endif
                                    @endif
                                @endcan
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="px-4 py-6 text-center text-slate-500">{{ __('Nenhum turno neste escopo.') }}</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </flux:card>

    <flux:modal wire:model.self="showChecklistModal" wire:close="closeChecklistModal" variant="floating" class="max-w-2xl">
        <div class="space-y-4">
            <div>
                <flux:heading size="lg">{{ __('Check-list da viatura') }}</flux:heading>
                <flux:text class="mt-1 text-slate-600 dark:text-slate-400">
                    {{ __('Selecione os recursos desta viatura e informe a quantidade de cada um.') }}
                </flux:text>
            </div>

            @error('checklist')
                <flux:callout variant="danger">{{ $message }}</flux:callout>
            @enderror

            @error('checklistSelectedResourceIds')
                <flux:callout variant="danger">{{ $message }}</flux:callout>
            @enderror

            @if ($checklistResources->isEmpty())
                <flux:callout variant="warning">
                    {{ __('Nenhum recurso cadastrado para esta base.') }}
                    @if (auth()->user()?->isOperationalCentral())
                        <flux:link :href="route('operations.parameters.checklist-resources')" wire:navigate class="ms-1">{{ __('Cadastrar recursos') }}</flux:link>
                    @endif
                </flux:callout>
            @else
                <div class="overflow-x-auto rounded-xl border border-slate-200 dark:border-slate-700">
                    <table class="min-w-full divide-y divide-slate-200 text-sm dark:divide-slate-700">
                        <thead class="bg-slate-50 dark:bg-slate-900/50">
                            <tr>
                                <th class="px-3 py-2 text-start font-medium">{{ __('Usar') }}</th>
                                <th class="px-3 py-2 text-start font-medium">{{ __('Item') }}</th>
                                <th class="px-3 py-2 text-start font-medium">{{ __('Unidade') }}</th>
                                <th class="px-3 py-2 text-start font-medium">{{ __('Quantidade') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                            @foreach ($checklistResources as $resource)
                                @php
                                    $isSelected = in_array($resource->id, $checklistSelectedResourceIds, true)
                                        || in_array((string) $resource->id, $checklistSelectedResourceIds, true);
                                @endphp
                                <tr wire:key="checklist-resource-{{ $resource->id }}">
                                    <td class="px-3 py-2">
                                        <input
                                            type="checkbox"
                                            wire:model.live="checklistSelectedResourceIds"
                                            value="{{ $resource->id }}"
                                            class="h-4 w-4 rounded border-zinc-300 text-blue-600 focus:ring-blue-500"
                                        />
                                    </td>
                                    <td class="px-3 py-2 font-medium">{{ $resource->name }}</td>
                                    <td class="px-3 py-2 text-slate-600 dark:text-slate-400">{{ $resource->unit_of_measure }}</td>
                                    <td class="px-3 py-2">
                                        <flux:input
                                            type="number"
                                            min="0"
                                            step="1"
                                            wire:model="checklistQuantities.{{ $resource->id }}"
                                            placeholder="0"
                                            :disabled="! $isSelected"
                                        />
                                        @error('checklistQuantities.'.$resource->id)
                                            <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                                        @enderror
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <flux:textarea
                    wire:model="checklistObservation"
                    :label="__('Observação (obrigatória se o check-list não for concluído)')"
                    rows="2"
                />
                @error('checklistObservation')
                    <flux:callout variant="danger">{{ $message }}</flux:callout>
                @enderror

                <div class="flex flex-wrap justify-end gap-2">
                    <flux:button variant="ghost" wire:click="closeChecklistModal">{{ __('Fechar') }}</flux:button>
                    <flux:button variant="filled" wire:click="saveChecklist(false)">{{ __('Salvar pendente') }}</flux:button>
                    <flux:button variant="primary" wire:click="saveChecklist(true)">{{ __('Concluir check-list') }}</flux:button>
                </div>
            @endif
        </div>
    </flux:modal>
</div>
