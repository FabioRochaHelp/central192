@php
    use App\Support\Operations\VehicleTrackerStatusQuery;
@endphp

<flux:card class="flex max-h-[min(64vh,38rem)] flex-col gap-3 !p-3 shadow-sm">
    <div class="flex items-center justify-between gap-2">
        <div class="flex items-center gap-2">
            <flux:icon.users class="size-4 text-blue-700 dark:text-blue-300" />
            <flux:subheading>{{ __('Turnos') }}</flux:subheading>
        </div>
        <flux:badge size="sm" color="green" inset>{{ __('Disponíveis') }}</flux:badge>
    </div>

    <div class="min-h-0 flex-1 space-y-2 overflow-y-auto pe-1">
        @forelse ($availableShifts as $shift)
            @php
                $staffTooltip = $shift->staff?->isNotEmpty()
                    ? $shift->staff
                        ->map(fn ($p) => trim($p->name.($p->cargo ? ' · '.$p->cargo->label() : '')))
                        ->implode("\n")
                    : __('Sem efetivo vinculado');
                $trackerStatus = $trackerStatusByVehicleId[$shift->vehicle_id] ?? VehicleTrackerStatusQuery::Unknown;
                $trackerTitle = VehicleTrackerStatusQuery::label($trackerStatus);
            @endphp
            <div
                wire:key="disp-shift-{{ $shift->id }}"
                class="flex flex-col gap-1 rounded-xl border border-slate-200/90 bg-white/90 px-2.5 py-2.5 text-sm dark:border-slate-700/60 dark:bg-slate-900/40"
            >
                <div class="flex items-center justify-between gap-2">
                    <div class="flex min-w-0 items-center gap-2">
                        <flux:icon.truck class="size-4 text-slate-500 dark:text-slate-400" />
                        <span class="truncate font-semibold text-slate-900 dark:text-slate-50">{{ $shift->vehicle?->prefix ?? __('Sem prefixo') }}</span>
                    </div>
                    <span class="text-xs font-medium tabular-nums text-slate-500 dark:text-slate-400">#{{ $shift->id }}</span>
                </div>
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <span class="text-xs text-slate-500">{{ $shift->municipio?->razao_social ?? ('#'.$shift->municipio_id) }}</span>
                    <div class="flex items-center gap-2">
                        <button
                            type="button"
                            wire:click="openShiftChecklistModal({{ $shift->id }})"
                            @class([
                                'inline-flex items-center justify-center rounded-lg border p-1 transition',
                                'border-emerald-200 bg-emerald-50 text-emerald-700 hover:border-emerald-300 dark:border-emerald-800 dark:bg-emerald-900/20 dark:text-emerald-300' => $shift->isChecklistComplete(),
                                'border-amber-200 bg-amber-50 text-amber-700 hover:border-amber-300 dark:border-amber-800 dark:bg-amber-900/20 dark:text-amber-300' => ! $shift->isChecklistComplete(),
                            ])
                            title="{{ $shift->isChecklistComplete() ? __('Check-list concluído') : ($shift->checklist_observation ?: __('Check-list pendente')) }}"
                        >
                            <flux:icon.clipboard-document-check class="size-4" />
                        </button>
                        <span
                            @class([
                                'inline-flex items-center justify-center rounded-lg border p-1',
                                'border-emerald-200 bg-emerald-50 text-emerald-700 dark:border-emerald-800 dark:bg-emerald-900/20 dark:text-emerald-300' => $trackerStatus === VehicleTrackerStatusQuery::Online,
                                'border-red-200 bg-red-50 text-red-700 dark:border-red-800 dark:bg-red-900/20 dark:text-red-300' => $trackerStatus === VehicleTrackerStatusQuery::Offline,
                                'border-slate-200 bg-slate-50 text-slate-500 dark:border-slate-700 dark:bg-slate-800/40 dark:text-slate-400' => $trackerStatus === VehicleTrackerStatusQuery::NoDevice,
                                'border-amber-200 bg-amber-50 text-amber-700 dark:border-amber-800 dark:bg-amber-900/20 dark:text-amber-300' => $trackerStatus === VehicleTrackerStatusQuery::Unknown,
                            ])
                            title="{{ $trackerTitle }}"
                            data-test="shift-tracker-status-{{ $shift->id }}"
                        >
                            @if ($trackerStatus === VehicleTrackerStatusQuery::Online)
                                <flux:icon.signal class="size-4" />
                            @else
                                <flux:icon.signal-slash class="size-4" />
                            @endif
                        </span>
                        <span class="inline-flex items-center gap-1 text-xs font-medium text-slate-600 dark:text-slate-400" title="{{ $staffTooltip }}">
                            <flux:icon.users class="size-4 text-slate-400 dark:text-slate-500" />
                            <span class="tabular-nums">{{ (int) ($shift->staff_count ?? 0) }}</span>
                        </span>
                    </div>
                </div>
                @if (! $shift->isChecklistComplete() && $shift->checklist_observation)
                    <p class="text-[11px] leading-snug text-amber-700 dark:text-amber-300" title="{{ $shift->checklist_observation }}">
                        {{ \Illuminate\Support\Str::limit($shift->checklist_observation, 80) }}
                    </p>
                @endif
            </div>
        @empty
            <flux:text size="sm" class="py-4 text-center text-slate-500">{{ __('Nenhum turno disponível no escopo.') }}</flux:text>
        @endforelse
    </div>
</flux:card>
