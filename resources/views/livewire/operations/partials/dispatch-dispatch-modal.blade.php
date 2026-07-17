<flux:modal wire:model.self="showDispatchModal" wire:close="closeDispatchModal" variant="floating" class="max-w-4xl !p-0">
    @if ($modalIncident !== null)
        @php
            $callType = \App\Domain\Operations\Enums\CallType::tryFrom((string) $modalIncident->patient_call_type);
            $accent = $callType?->dispatchQueueAccentClasses() ?? \App\Domain\Operations\Enums\CallType::Normal->dispatchQueueAccentClasses();
            $callTypeInitial = $modalIncident->patient_call_type ?: '—';
            $callTypeLabel = $callType?->label() ?? __('Sem classificação');
            $address = collect([$modalIncident->address_line, $modalIncident->city])->filter()->join(', ');
        @endphp

        <div class="overflow-hidden rounded-2xl">
            <div class="border-b border-slate-200/90 px-6 py-5 dark:border-slate-700/80 {{ $accent['row'] }}">
                <div class="flex flex-wrap items-start justify-between gap-4">
                    <div class="flex min-w-0 items-start gap-3">
                        <span
                            class="inline-flex h-12 min-w-12 shrink-0 items-center justify-center rounded-xl border px-2 font-mono text-xl font-bold {{ $accent['badge'] }}"
                            title="{{ $callTypeLabel }}"
                        >
                            {{ $callTypeInitial }}
                        </span>
                        <div class="min-w-0">
                            <div class="flex flex-wrap items-center gap-2">
                                <flux:icon.truck class="size-5 text-emerald-700 dark:text-emerald-300" />
                                <flux:heading size="lg">
                                    {{ $dispatchModalIsSupport ? __('Empenhar apoio') : __('Empenhar viatura') }}
                                </flux:heading>
                            </div>
                            <flux:heading size="xl" class="mt-1 tabular-nums">
                                {{ __('Talão :talao/:ano', ['talao' => $modalIncident->talao, 'ano' => $modalIncident->dispatch_year]) }}
                            </flux:heading>
                            <flux:text class="mt-1 font-medium text-slate-700 dark:text-slate-200">
                                {{ $modalIncident->occurred_at->format('d/m/Y H:i') }}
                                @if ($modalIncident->municipio?->razao_social)
                                    <span class="text-slate-500 dark:text-slate-400">· {{ $modalIncident->municipio->razao_social }}</span>
                                @endif
                            </flux:text>
                        </div>
                    </div>
                    <div class="flex flex-wrap items-center justify-end gap-2">
                        <x-incident.manchester-badge :risk="$modalIncident->manchester_risk" :showPrefix="false" />
                        <flux:badge color="green" size="sm">{{ __('Disponível para empenho') }}</flux:badge>
                    </div>
                </div>
            </div>

            <div class="space-y-4 bg-white p-6 dark:bg-slate-950">
                <div class="grid gap-3 sm:grid-cols-2">
                    <div class="rounded-xl border border-slate-200/90 bg-slate-50/80 p-3 dark:border-slate-700 dark:bg-slate-900/50">
                        <div class="flex items-center gap-2 text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">
                            <flux:icon.bolt class="size-4 shrink-0" />
                            {{ __('Natureza') }}
                        </div>
                        <p class="mt-1.5 text-sm font-semibold text-slate-900 dark:text-slate-100">{{ $modalIncident->nature?->name ?? '—' }}</p>
                    </div>

                    <div class="rounded-xl border border-slate-200/90 bg-slate-50/80 p-3 dark:border-slate-700 dark:bg-slate-900/50">
                        <div class="flex items-center gap-2 text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">
                            <flux:icon.map-pin class="size-4 shrink-0" />
                            {{ __('Endereço') }}
                        </div>
                        <p class="mt-1.5 text-sm font-semibold text-slate-900 dark:text-slate-100">{{ $address ?: '—' }}</p>
                    </div>
                </div>

                @if ($dispatchModalIsSupport && $modalIncident->dispatches->whereNull('deleted_at')->isNotEmpty())
                    <flux:callout variant="info" icon="truck">
                        <div class="text-xs font-semibold uppercase tracking-wide">{{ __('Viaturas já empenhadas') }}</div>
                        <ul class="mt-2 space-y-1 text-sm">
                            @foreach ($modalIncident->dispatches->whereNull('deleted_at') as $activeDispatch)
                                <li wire:key="modal-active-dispatch-{{ $activeDispatch->id }}">
                                    {{ $activeDispatch->shift?->vehicle?->prefix ?? __('—') }}
                                    · {{ $activeDispatch->stage->label() }}
                                </li>
                            @endforeach
                        </ul>
                    </flux:callout>
                @endif

                @error('modalVehicleId')
                    <flux:callout variant="danger">{{ $message }}</flux:callout>
                @enderror

                @if ($modalShifts->isEmpty())
                    <flux:callout variant="warning" icon="exclamation-triangle">
                        {{ __('Não há turnos disponíveis nesta base para empenho.') }}
                    </flux:callout>
                @else

                    <div>
                        <div class="mb-2 flex flex-wrap items-center justify-between gap-2">
                            <flux:subheading>{{ __('Turnos disponíveis') }}</flux:subheading>
                            <flux:badge color="blue" size="sm">{{ trans_choice(':count viatura|:count viaturas', $modalShifts->count(), ['count' => $modalShifts->count()]) }}</flux:badge>
                        </div>

                        <div class="cco-table-shell max-h-[min(50vh,28rem)] overflow-y-auto">
                            <table class="min-w-full divide-y divide-slate-200/95 text-start text-sm dark:divide-slate-800/80">
                                <thead class="sticky top-0 z-10 bg-slate-100/95 backdrop-blur dark:bg-slate-900/95">
                                    <tr>
                                        <th class="w-12 px-3 py-2.5 text-center font-medium">{{ __('#') }}</th>
                                        <th class="px-3 py-2.5 font-medium">{{ __('Viatura') }}</th>
                                        <th class="px-3 py-2.5 font-medium">{{ __('Placa') }}</th>
                                        <th class="px-3 py-2.5 font-medium">{{ __('Na base desde') }}</th>
                                        <th class="px-3 py-2.5 text-center font-medium">{{ __('Efetivo') }}</th>
                                        <th class="px-3 py-2.5 text-center font-medium">{{ __('Check') }}</th>
                                        <th class="w-14 px-3 py-2.5 text-center font-medium">{{ __('Sel.') }}</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-200/95 dark:divide-slate-800/80">
                                    @foreach ($modalShifts as $shift)
                                        @php
                                            $isSelected = $modalVehicleId === $shift->vehicle_id;
                                            $availableAt = \App\Support\Operations\DispatchFairQueueShiftSorter::availableAt($shift);
                                            $staffTooltip = $shift->staff?->isNotEmpty()
                                                ? $shift->staff
                                                    ->map(fn ($p) => trim($p->name.($p->cargo ? ' · '.$p->cargo->label() : '')))
                                                    ->implode("\n")
                                                : __('Sem efetivo vinculado');
                                            $isNextInQueue = $loop->first;
                                            $distanceKm = $modalShiftDistances->get($shift->vehicle_id);
                                        @endphp
                                        <tr
                                            wire:key="modal-shift-{{ $shift->id }}"
                                            wire:click="$set('modalVehicleId', {{ $shift->vehicle_id }})"
                                            @class([
                                                'cursor-pointer transition-colors',
                                                'bg-emerald-50/90 dark:bg-emerald-950/25' => $isSelected,
                                                'hover:bg-slate-50 dark:hover:bg-slate-900/50' => ! $isSelected,
                                            ])
                                        >
                                            <td class="px-3 py-2.5 text-center">
                                                <span @class([
                                                    'inline-flex size-7 items-center justify-center rounded-full text-xs font-bold tabular-nums',
                                                    'bg-emerald-600 text-white' => $isNextInQueue,
                                                    'bg-slate-200 text-slate-700 dark:bg-slate-700 dark:text-slate-200' => ! $isNextInQueue,
                                                ])>
                                                    {{ $loop->iteration }}
                                                </span>
                                            </td>
                                            <td class="whitespace-nowrap px-3 py-2.5">
                                                <div class="flex items-center gap-2">
                                                    <span class="font-semibold text-slate-900 dark:text-slate-50">{{ $shift->vehicle?->prefix ?? __('Sem prefixo') }}</span>
                                                    @if ($isNextInQueue)
                                                        <flux:badge size="sm" color="green">{{ $distanceKm !== null ? __('Mais próxima') : __('Próxima') }}</flux:badge>
                                                    @endif
                                                    @if ($distanceKm !== null)
                                                        <span class="inline-flex items-center gap-1 text-xs font-medium tabular-nums text-blue-600 dark:text-blue-400">
                                                            <flux:icon.map-pin class="size-3.5" />
                                                            {{ number_format($distanceKm, 1, ',', '.') }} km
                                                        </span>
                                                    @endif
                                                </div>
                                                <span class="text-xs text-slate-500 dark:text-slate-400">{{ __('Turno') }} #{{ $shift->id }}</span>
                                            </td>
                                            <td class="whitespace-nowrap px-3 py-2.5 font-mono text-slate-700 dark:text-slate-300">
                                                {{ $shift->vehicle?->plate ?? '—' }}
                                            </td>
                                            <td class="whitespace-nowrap px-3 py-2.5">
                                                <span class="font-medium tabular-nums text-slate-900 dark:text-slate-100">{{ $availableAt->format('d/m H:i') }}</span>
                                                <span class="block text-xs text-slate-500 dark:text-slate-400">{{ $availableAt->diffForHumans(short: true) }}</span>
                                            </td>
                                            <td class="px-3 py-2.5 text-center" title="{{ $staffTooltip }}">
                                                <span class="inline-flex items-center gap-1 font-medium tabular-nums text-slate-700 dark:text-slate-300">
                                                    <flux:icon.users class="size-4 text-slate-400" />
                                                    {{ (int) ($shift->staff_count ?? 0) }}
                                                </span>
                                            </td>
                                            <td class="px-3 py-2.5 text-center">
                                                @if ($shift->isChecklistComplete())
                                                    <flux:badge size="sm" color="green" icon="clipboard-document-check">{{ __('OK') }}</flux:badge>
                                                @else
                                                    <flux:badge size="sm" color="amber" icon="clipboard-document-check" title="{{ $shift->checklist_observation }}">{{ __('Pend.') }}</flux:badge>
                                                @endif
                                            </td>
                                            <td class="px-3 py-2.5 text-center" @click.stop>
                                                <input
                                                    type="radio"
                                                    name="modalVehicleId"
                                                    value="{{ $shift->vehicle_id }}"
                                                    wire:model.live="modalVehicleId"
                                                    class="size-4 border-slate-300 text-emerald-600 focus:ring-emerald-500 dark:border-slate-600"
                                                />
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                @endif

                <div class="space-y-4 rounded-2xl border border-slate-200/90 bg-slate-50/80 p-4 dark:border-slate-800 dark:bg-slate-900/50">
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <div>
                            <flux:subheading>{{ __('Registro de contato pré-despacho') }}</flux:subheading>
                            <flux:text size="sm" class="text-slate-500 dark:text-slate-400">
                                {{ __('Registre o contato antes de empenhar a viatura mais próxima. Se não for possível, informe o motivo e acione o responsável pela viatura indicada.') }}
                            </flux:text>
                        </div>
                        @if ($modalShifts->isNotEmpty())
                            @php $nearestShift = $modalShifts->first(); @endphp
                            <div class="space-y-1 text-right text-sm text-slate-600 dark:text-slate-300">
                                <span class="inline-flex items-center gap-2 rounded-full bg-slate-100 px-3 py-1 dark:bg-slate-800">
                                    {{ __('Mais próxima:') }} {{ $nearestShift->vehicle?->prefix ?? __('Sem prefixo') }}
                                </span>
                                @if ($nearestShift->staff->isNotEmpty())
                                    <span>{{ __('Responsável:') }} {{ $nearestShift->staff->pluck('name')->join(', ') }}</span>
                                @endif
                            </div>
                        @endif
                    </div>

                    <div
                        class="grid gap-4 sm:grid-cols-2"
                        x-data="{
                            method: @entangle('dispatchContactMethod'),
                            vehicleId: @entangle('modalVehicleId'),
                            details: @entangle('dispatchContactDetails'),
                            suggestions: @js($modalContactSuggestions),
                            suggestion() {
                                const base = this.suggestions[this.vehicleId];
                                return base && this.method ? (base[this.method] ?? null) : null;
                            },
                            applySuggestion() {
                                const value = this.suggestion();
                                if (value) {
                                    this.details = value;
                                }
                            },
                        }"
                        x-init="
                            $watch('method', () => applySuggestion());
                            $watch('vehicleId', () => applySuggestion());
                        "
                    >
                        <flux:select wire:model.live="dispatchContactMethod" :label="__('Método de contato')" placeholder="{{ __('Selecione') }}">
                            @foreach (App\Livewire\Operations\DispatchBoard::DISPATCH_CONTACT_METHODS as $method => $label)
                                <flux:select.option value="{{ $method }}">{{ $label }}</flux:select.option>
                            @endforeach
                        </flux:select>

                        <div>
                            <flux:input x-model="details" :label="__('Número / ramal / link')" placeholder="{{ __('Ex: 192, 99888-7777 ou wa.me/...') }}" />
                            <template x-if="suggestion() && details === suggestion()">
                                <flux:text size="xs" class="mt-1 text-emerald-600 dark:text-emerald-400">
                                    ✓ {{ __('Sugestão da base da viatura (pré-preenchida)') }}
                                </flux:text>
                            </template>
                            <template x-if="suggestion() && details !== suggestion()">
                                <flux:text size="xs" class="mt-1 text-slate-500 dark:text-slate-400">
                                    {{ __('Sugestão da base:') }} <span x-text="suggestion()"></span>
                                </flux:text>
                            </template>
                        </div>
                    </div>

                    <div class="grid gap-3 sm:grid-cols-2">
                        <div class="space-y-2">
                            <flux:text class="font-medium text-slate-700 dark:text-slate-200">{{ __('Resultado do contato') }}</flux:text>
                            <div class="grid gap-2 sm:grid-cols-2">
                                <label class="flex items-center gap-2 rounded-xl border border-slate-200/90 bg-white px-3 py-3 text-sm text-slate-700 transition hover:border-slate-300 dark:border-slate-700 dark:bg-slate-950 dark:text-slate-200">
                                    <input type="radio" name="dispatchContactSuccessful" value="1" wire:model.live="dispatchContactSuccessful" class="h-4 w-4 rounded border-slate-300 text-emerald-600 focus:ring-emerald-500" />
                                    {{ __('Contato efetuado') }}
                                </label>
                                <label class="flex items-center gap-2 rounded-xl border border-slate-200/90 bg-white px-3 py-3 text-sm text-slate-700 transition hover:border-slate-300 dark:border-slate-700 dark:bg-slate-950 dark:text-slate-200">
                                    <input type="radio" name="dispatchContactSuccessful" value="0" wire:model.live="dispatchContactSuccessful" class="h-4 w-4 rounded border-slate-300 text-emerald-600 focus:ring-emerald-500" />
                                    {{ __('Não foi possível contatar') }}
                                </label>
                            </div>
                        </div>

                        @if (! $dispatchContactSuccessful)
                            <flux:textarea wire:model.live="dispatchContactReason" :label="__('Motivo')" rows="4" placeholder="{{ __('Ex: ocupada, não atende, número inválido') }}" />
                        @endif
                    </div>

                    @error('dispatchContactMethod')
                        <flux:callout variant="danger">{{ $message }}</flux:callout>
                    @enderror
                    @error('dispatchContactDetails')
                        <flux:callout variant="danger">{{ $message }}</flux:callout>
                    @enderror
                    @error('dispatchContactReason')
                        <flux:callout variant="danger">{{ $message }}</flux:callout>
                    @enderror
                </div>

                <div class="flex flex-wrap justify-end gap-2 border-t border-slate-200/90 pt-4 dark:border-slate-800">
                    <flux:modal.close>
                        <flux:button variant="outline" type="button" class="min-w-28">
                            {{ __('Cancelar') }}
                        </flux:button>
                    </flux:modal.close>

                    @if ($modalShifts->isEmpty() || $modalVehicleId === null)
                        <flux:button
                            variant="primary"
                            color="emerald"
                            icon="paper-airplane"
                            type="button"
                            disabled
                            wire:loading.attr="disabled"
                            class="min-w-40 opacity-60"
                        >
                            {{ __('Registrar contato') }}
                        </flux:button>
                    @else
                        <flux:button
                            variant="primary"
                            color="emerald"
                            icon="paper-airplane"
                            type="button"
                            wire:click="confirmDispatch"
                            wire:loading.attr="disabled"
                            class="min-w-40"
                        >
                            {{ $dispatchContactSuccessful ? __('Confirmar contato e empenhar') : __('Registrar tentativa sem empenho') }}
                        </flux:button>
                    @endif
                </div>
            </div>
        </div>
    @endif
</flux:modal>
