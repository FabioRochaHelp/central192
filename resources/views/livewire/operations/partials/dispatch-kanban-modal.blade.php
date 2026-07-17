<flux:modal wire:model.self="showKanbanModal" wire:close="closeKanbanModal" variant="floating" class="max-w-4xl !p-0">
    @if ($kanbanDispatch !== null && $kanbanDispatch->incident !== null)
        @php
            $incident = $kanbanDispatch->incident;
            $callType = \App\Domain\Operations\Enums\CallType::tryFrom((string) $incident->patient_call_type);
            $accent = $callType?->dispatchQueueAccentClasses() ?? \App\Domain\Operations\Enums\CallType::Normal->dispatchQueueAccentClasses();
            $callTypeInitial = $incident->patient_call_type ?: '—';
            $callTypeLabel = $callType?->label() ?? __('Sem classificação');
            $address = collect([$incident->address_line, $incident->city])->filter()->join(', ');
            $canAdvance = auth()->user()?->can('advanceStage', $incident);
            $canObserve = auth()->user()?->can('addObservation', $incident);
            $canRelease = auth()->user()?->can('releaseUnit', $incident)
                && ($kanbanFireMeta['canRelease'] ?? false);
            $nextStage = $kanbanDispatch->stage->next();
            $isReleasedFromHospital = $kanbanDispatch->stage === \App\Domain\Operations\Enums\DispatchStage::ReleasedHospital;
            $canCancelAtScene = $canAdvance && $kanbanDispatch->stage->index() < \App\Domain\Operations\Enums\DispatchStage::LeftScene->index();
            $canSupportDispatch = auth()->user()?->can('dispatchUnit', $incident)
                && in_array($incident->status->value, ['dispatched', 'in_progress'], true);
            $activeDispatches = $incident->dispatches->whereNull('deleted_at');
        @endphp

        <div class="overflow-hidden rounded-2xl">
            <div class="border-b border-slate-200/90 px-6 py-5 dark:border-slate-700/80 {{ $accent['row'] }}">
                <div class="flex flex-wrap items-start justify-between gap-4">
                    <div class="flex min-w-0 items-start gap-3">
                        <span
                            class="inline-flex h-11 min-w-11 shrink-0 items-center justify-center rounded-xl border px-2 font-mono text-lg font-bold {{ $accent['badge'] }}"
                            title="{{ $callTypeLabel }}"
                        >
                            {{ $callTypeInitial }}
                        </span>
                        <div class="min-w-0">
                            <flux:heading size="lg" class="tabular-nums">
                                {{ __('Talão :talao/:ano', ['talao' => $incident->talao, 'ano' => $incident->dispatch_year]) }}
                            </flux:heading>
                            <flux:text class="mt-1 font-medium text-slate-700 dark:text-slate-200">
                                {{ $kanbanDispatch->shift?->vehicle?->prefix ?? __('Sem viatura') }}
                                · {{ $kanbanDispatch->stage->label() }}
                            </flux:text>
                        </div>
                    </div>
                    <div class="flex flex-wrap items-center justify-end gap-2">
                        <flux:badge color="cyan">{{ $kanbanDispatch->stage->label() }}</flux:badge>
                        <x-incident.manchester-badge :risk="$incident->manchester_risk" :showPrefix="false" />
                    </div>
                </div>
            </div>

            <div class="space-y-5 bg-white p-6 dark:bg-slate-950">
                <div class="grid gap-3 sm:grid-cols-2">
                    <div class="rounded-xl border border-slate-200/90 bg-slate-50/80 p-3 dark:border-slate-700 dark:bg-slate-900/50">
                        <div class="flex items-center gap-2 text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">
                            <flux:icon.bolt class="size-4 shrink-0" />
                            {{ __('Natureza') }}
                        </div>
                        <p class="mt-1.5 text-sm font-semibold text-slate-900 dark:text-slate-100">{{ $incident->nature?->name ?? '—' }}</p>
                    </div>

                    <div class="rounded-xl border border-slate-200/90 bg-slate-50/80 p-3 dark:border-slate-700 dark:bg-slate-900/50">
                        <div class="flex items-center gap-2 text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">
                            <flux:icon.map-pin class="size-4 shrink-0" />
                            {{ __('Endereço') }}
                        </div>
                        <p class="mt-1.5 text-sm font-semibold text-slate-900 dark:text-slate-100">{{ $address ?: '—' }}</p>
                    </div>
                </div>

                @if ($incident->description)
                    <flux:callout class="border-s-blue-200 bg-blue-50/80 dark:border-blue-900/60 dark:bg-blue-950/30">
                        <flux:text class="whitespace-pre-wrap text-slate-800 dark:text-slate-100">{{ $incident->description }}</flux:text>
                    </flux:callout>
                @endif

                @if ($canSupportDispatch)
                    <div>
                        {{-- Botão "Empenhar apoio" fixo sempre visível, mostra/oculta o painel de apoio --}}
                        <div class="mb-3 flex w-full justify-center sm:justify-end">
                            <flux:button
                                variant="{{ $showKanbanSupportPanel ? 'outline' : 'primary' }}"
                                color="emerald"
                                icon="{{ $showKanbanSupportPanel ? 'chevron-up' : 'truck' }}"
                                wire:click="toggleKanbanSupportPanel"
                                class="w-full sm:w-auto transition-all duration-200"
                                aria-expanded="{{ $showKanbanSupportPanel ? 'true' : 'false' }}"
                            >
                                {{ $showKanbanSupportPanel ? __('Ocultar apoio') : __('Empenhar apoio') }}
                            </flux:button>
                        </div>

                        @if ($activeDispatches->count() > 1 || ($activeDispatches->count() === 1 && $showKanbanSupportPanel))
                            <flux:callout variant="info" icon="truck" class="!py-2">
                                <div class="text-xs font-semibold uppercase tracking-wide">{{ __('Viaturas empenhadas') }}</div>
                                <ul class="mt-1 space-y-0.5 text-sm">
                                    @foreach ($activeDispatches as $activeDispatch)
                                        <li wire:key="kanban-active-dispatch-{{ $activeDispatch->id }}">
                                            {{ $activeDispatch->shift?->vehicle?->prefix ?? __('—') }}
                                            · {{ $activeDispatch->stage->label() }}
                                            @if ($activeDispatch->id === $kanbanDispatch->id)
                                                <span class="text-emerald-700 dark:text-emerald-300">({{ __('esta viatura') }})</span>
                                            @endif
                                        </li>
                                    @endforeach
                                </ul>
                            </flux:callout>
                        @endif

                        {{-- Div de apoio controlada via $showKanbanSupportPanel --}}
                        <div x-data="{ show: @entangle('showKanbanSupportPanel') }">
                            <div x-show="show" x-transition>
                                <div class="space-y-3 rounded-xl border border-emerald-200/90 bg-emerald-50/50 p-4 dark:border-emerald-900/50 dark:bg-emerald-950/20 mt-3">
                                    @error('kanbanSupportVehicleId')
                                        <flux:callout variant="danger">{{ $message }}</flux:callout>
                                    @enderror

                                    @if ($kanbanSupportShifts->isEmpty())
                                        <flux:callout variant="warning" icon="exclamation-triangle">
                                            {{ __('Não há turnos disponíveis para apoio nesta base.') }}
                                        </flux:callout>
                                    @else
                                        <div class="cco-table-shell max-h-[min(40vh,20rem)] overflow-y-auto rounded-lg border border-slate-200/90 dark:border-slate-700">
                                            <table class="min-w-full divide-y divide-slate-200/95 text-start text-sm dark:divide-slate-800/80">
                                                <thead class="sticky top-0 z-10 bg-slate-100/95 backdrop-blur dark:bg-slate-900/95">
                                                    <tr>
                                                        <th class="px-3 py-2 font-medium">{{ __('Viatura') }}</th>
                                                        <th class="px-3 py-2 font-medium">{{ __('Placa') }}</th>
                                                        <th class="px-3 py-2 font-medium">{{ __('Na base desde') }}</th>
                                                        <th class="w-14 px-3 py-2 text-center font-medium">{{ __('Sel.') }}</th>
                                                    </tr>
                                                </thead>
                                                <tbody class="divide-y divide-slate-200/95 dark:divide-slate-800/80">
                                                    @foreach ($kanbanSupportShifts as $shift)
                                                        @php
                                                            $isSelected = $kanbanSupportVehicleId === $shift->vehicle_id;
                                                            $availableAt = \App\Support\Operations\DispatchFairQueueShiftSorter::availableAt($shift);
                                                        @endphp
                                                        <tr
                                                            wire:key="kanban-support-shift-{{ $shift->id }}"
                                                            wire:click="$set('kanbanSupportVehicleId', {{ $shift->vehicle_id }})"
                                                            @class([
                                                                'cursor-pointer transition-colors',
                                                                'bg-emerald-50/90 dark:bg-emerald-950/25' => $isSelected,
                                                                'hover:bg-slate-50 dark:hover:bg-slate-900/50' => ! $isSelected,
                                                            ])
                                                        >
                                                            <td class="whitespace-nowrap px-3 py-2 font-semibold text-slate-900 dark:text-slate-50">
                                                                {{ $shift->vehicle?->prefix ?? __('Sem prefixo') }}
                                                            </td>
                                                            <td class="whitespace-nowrap px-3 py-2 font-mono text-slate-700 dark:text-slate-300">
                                                                {{ $shift->vehicle?->plate ?? '—' }}
                                                            </td>
                                                            <td class="whitespace-nowrap px-3 py-2 tabular-nums text-slate-700 dark:text-slate-300">
                                                                {{ $availableAt->format('d/m H:i') }}
                                                            </td>
                                                            <td class="px-3 py-2 text-center" @click.stop>
                                                                <input
                                                                    type="radio"
                                                                    name="kanbanSupportVehicleId"
                                                                    value="{{ $shift->vehicle_id }}"
                                                                    wire:model.live="kanbanSupportVehicleId"
                                                                    class="size-4 border-slate-300 text-emerald-600 focus:ring-emerald-500 dark:border-slate-600"
                                                                />
                                                            </td>
                                                        </tr>
                                                    @endforeach
                                                </tbody>
                                            </table>
                                        </div>

                                        <div class="flex justify-end">
                                            @if ($kanbanSupportVehicleId === null)
                                                <flux:button
                                                    variant="primary"
                                                    color="emerald"
                                                    icon="paper-airplane"
                                                    type="button"
                                                    disabled
                                                    class="opacity-60"
                                                >
                                                    {{ __('Confirmar apoio') }}
                                                </flux:button>
                                            @else
                                                <flux:button
                                                    variant="primary"
                                                    color="emerald"
                                                    icon="paper-airplane"
                                                    wire:click="confirmKanbanSupportDispatch"
                                                    wire:loading.attr="disabled"
                                                >
                                                    {{ __('Confirmar apoio') }}
                                                </flux:button>
                                            @endif
                                        </div>
                                    @endif
                                </div>
                            </div>
                        </div>
                    </div>
                @endif

                @if ($canRelease)
                    <flux:callout variant="{{ $isReleasedFromHospital ? 'success' : 'info' }}" icon="home">
                        {{ $isReleasedFromHospital
                            ? __('Viatura aguardando encerramento para retornar à fila de empenho.')
                            : __('Encerre na base para disponibilizar a viatura.') }}
                    </flux:callout>
                @endif

                <div class="grid gap-2 border-t border-slate-200/90 pt-4 dark:border-slate-800 sm:grid-cols-2">
                    @if ($canRelease)
                        <flux:button
                            variant="primary"
                            color="emerald"
                            icon="home"
                            wire:click="releaseKanbanUnit"
                            wire:confirm="{{ __('Encerrar ocorrência e disponibilizar a viatura na base?') }}"
                            class="w-full"
                        >
                            {{ __('Encerrar e disponibilizar viatura') }}
                        </flux:button>
                    @endif

                    @if ($canAdvance && $nextStage !== null && ! (($kanbanFireMeta['closesAtLeftScene'] ?? false) && $kanbanDispatch->stage === \App\Domain\Operations\Enums\DispatchStage::LeftScene))
                        <flux:button
                            variant="primary"
                            color="blue"
                            icon="arrow-right"
                            wire:click="advanceKanbanStage"
                            class="w-full"
                        >
                            {{ __('Avançar para') }}: {{ $nextStage->label() }}
                        </flux:button>
                    @endif

                    @if ($canObserve)
                        <flux:button
                            variant="primary"
                            color="amber"
                            icon="chat-bubble-left-ellipsis"
                            wire:click="openObservationFromKanban"
                            class="w-full"
                        >
                            {{ __('Adicionar à descrição') }}
                        </flux:button>
                    @endif

                    <flux:button
                        variant="primary"
                        color="blue"
                        :href="route('operations.incidents.show', $incident)"
                        icon="arrow-top-right-on-square"
                        wire:navigate
                        class="w-full"
                    >
                        {{ __('Abrir ocorrência') }}
                    </flux:button>

                    <flux:button
                        variant="outline"
                        wire:click="closeKanbanModal"
                        class="w-full sm:col-span-2"
                    >
                        {{ __('Fechar') }}
                    </flux:button>
                </div>

                @if ($canCancelAtScene)
                    <div class="space-y-3 border-t border-slate-200/90 pt-4 dark:border-slate-800">
                        <flux:subheading>{{ __('Cancelar no local') }}</flux:subheading>
                        <flux:text size="sm" class="text-slate-600 dark:text-slate-400">
                            {{ __('Registra saída do local no kanban e marca a ocorrência como QTA.') }}
                        </flux:text>
                        <div class="grid gap-2 sm:grid-cols-2">
                            @foreach ($kanbanCancelReasons as $cancelReason)
                                <flux:button
                                    wire:key="kanban-cancel-{{ $cancelReason->value }}"
                                    variant="danger"
                                    icon="x-circle"
                                    wire:click="cancelDispatchAtScene('{{ $cancelReason->value }}')"
                                    wire:confirm="{{ __('Confirmar cancelamento: :reason?', ['reason' => $cancelReason->label()]) }}"
                                    class="w-full"
                                >
                                    {{ $cancelReason->label() }}
                                </flux:button>
                            @endforeach
                        </div>
                    </div>
                @endif
            </div>
        </div>
    @endif
</flux:modal>
