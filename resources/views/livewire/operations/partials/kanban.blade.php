<div class="cco-kanban-board">
    <div class="cco-kanban-board__scroll">
        <div class="cco-kanban-board__track">
            @foreach ($orderedStages as $stage)
                <flux:card
                    wire:key="kanban-stage-{{ $stage->value }}"
                    class="cco-kanban-column flex min-h-52 flex-col gap-2 !p-2.5"
                >
                    <div class="flex items-center justify-between gap-1 px-0.5">
                        <flux:badge color="cyan" size="sm">{{ $stage->label() }}</flux:badge>
                        <flux:text size="sm" class="tabular-nums text-slate-500">{{ $kanbanDispatches->get($stage->value, collect())->count() }}</flux:text>
                    </div>

                    <div
                        wire:sort="moveKanbanDispatch"
                        wire:sort:group="dispatch-kanban"
                        wire:sort:group-id="{{ $stage->value }}"
                        class="flex min-h-[9rem] flex-1 flex-col gap-1.5"
                    >
                        @forelse ($kanbanDispatches->get($stage->value, collect()) as $dispatch)
                            @php
                                $callType = \App\Domain\Operations\Enums\CallType::tryFrom((string) $dispatch->incident?->patient_call_type);
                                $accent = $callType?->dispatchQueueAccentClasses() ?? \App\Domain\Operations\Enums\CallType::Normal->dispatchQueueAccentClasses();
                                $prefix = $dispatch->shift?->vehicle?->prefix ?? __('—');
                                $cardMeta = $dispatchFireMeta[$dispatch->id] ?? ['canRelease' => false];
                                $canReleaseCard = auth()->user()?->can('releaseUnit', $dispatch->incident) && ($cardMeta['canRelease'] ?? false);
                                $unseenNotes = $dispatchUnseenNoteCounts[$dispatch->id] ?? 0;
                            @endphp
                            <div
                                wire:sort:item="{{ $dispatch->id }}"
                                wire:key="dispatch-{{ $dispatch->id }}"
                                class="cco-kanban-card group flex items-stretch gap-1 !p-1.5 {{ $accent['row'] }}"
                            >
                                <button
                                    type="button"
                                    wire:sort:handle
                                    class="flex shrink-0 cursor-grab items-center rounded-md px-1 text-slate-400 hover:bg-black/5 hover:text-slate-600 active:cursor-grabbing dark:hover:bg-white/10 dark:hover:text-slate-200"
                                    title="{{ __('Arrastar') }}"
                                >
                                    <flux:icon.bars-3 class="size-4" />
                                </button>

                                <button
                                    type="button"
                                    wire:click="openKanbanModal({{ $dispatch->id }})"
                                    class="flex min-w-0 flex-1 flex-col items-start gap-0.5 rounded-md px-1 py-0.5 text-start transition hover:brightness-[0.98] dark:hover:brightness-110"
                                    title="{{ __('Talão') }} #{{ $dispatch->incident?->talao }}/{{ $dispatch->incident?->dispatch_year }}"
                                >
                                    <span class="inline-flex max-w-full items-center gap-1">
                                        <span class="truncate rounded-md border px-1.5 py-0.5 font-mono text-xs font-bold {{ $accent['badge'] }}">
                                            {{ $prefix }}
                                        </span>
                                    </span>
                                    <span class="truncate text-[10px] font-medium tabular-nums text-slate-600 dark:text-slate-400">
                                        #{{ $dispatch->incident?->talao }}/{{ $dispatch->incident?->dispatch_year }}
                                    </span>
                                    @if ($canReleaseCard)
                                        <flux:button
                                            size="xs"
                                            variant="primary"
                                            color="emerald"
                                            icon="home"
                                            wire:click.stop="releaseIncident({{ $dispatch->incident_id }}, {{ $dispatch->shift->vehicle_id }})"
                                            wire:confirm="{{ __('Encerrar e disponibilizar viatura?') }}"
                                            class="mt-1 w-full"
                                        >
                                            {{ __('Encerrar') }}
                                        </flux:button>
                                    @endif
                                </button>

                                @if ($unseenNotes > 0)
                                    <button
                                        type="button"
                                        wire:click.stop="openNotesFromKanbanCard({{ $dispatch->id }})"
                                        class="flex shrink-0 animate-pulse items-center gap-0.5 self-start rounded-full border border-amber-500 bg-amber-100 px-1.5 py-0.5 text-[10px] font-bold tabular-nums text-amber-900 transition hover:animate-none hover:bg-amber-200 dark:border-amber-500/80 dark:bg-amber-950/70 dark:text-amber-200 dark:hover:bg-amber-900"
                                        title="{{ trans_choice('{1} :count anotação nova para repassar à guarnição|[2,*] :count anotações novas para repassar à guarnição', $unseenNotes) }}"
                                    >
                                        <flux:icon.chat-bubble-left-ellipsis class="size-3" />
                                        {{ $unseenNotes }}
                                    </button>
                                @endif
                            </div>
                        @empty
                            <flux:text size="sm" class="px-1 py-6 text-center text-slate-500">{{ __('—') }}</flux:text>
                        @endforelse
                    </div>
                </flux:card>
            @endforeach
        </div>
    </div>
</div>
