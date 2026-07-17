@php
    $idleTotal = $vehiclesWithoutShift->count() + $operationalIdleShifts->count();
@endphp

<flux:card class="flex max-h-[min(64vh,38rem)] flex-col gap-3 !p-3 shadow-sm">
    <div class="flex items-center justify-between gap-2">
        <div class="flex items-center gap-2">
            <flux:icon.truck class="size-4 text-blue-700 dark:text-blue-300" />
            <flux:subheading>{{ __('Viaturas') }}</flux:subheading>
        </div>
        <flux:badge size="sm" color="zinc" inset>{{ $idleTotal }}</flux:badge>
    </div>

    <div class="min-h-0 flex-1 space-y-2 overflow-y-auto pe-1">
        @foreach ($vehiclesWithoutShift as $vehicle)
            <div
                wire:key="idle-vehicle-{{ $vehicle->id }}"
                class="rounded-xl border border-amber-300/90 bg-amber-50/90 px-2.5 py-2.5 text-sm dark:border-amber-800/60 dark:bg-amber-950/25"
            >
                <div class="flex items-center justify-between gap-2">
                    <div class="flex min-w-0 items-center gap-2">
                        <flux:icon.exclamation-triangle class="size-4 text-amber-600 dark:text-amber-400" />
                        <span class="truncate font-semibold text-slate-900 dark:text-slate-50">{{ $vehicle->prefix ?? __('Sem prefixo') }}</span>
                    </div>
                    <flux:badge size="sm" color="amber">{{ __('Sem turno') }}</flux:badge>
                </div>
                <span class="mt-1 block text-xs text-slate-500">{{ $vehicle->municipio?->razao_social ?? ('#'.$vehicle->municipio_id) }}</span>
            </div>
        @endforeach

        @foreach ($operationalIdleShifts as $shift)
            @php
                $accent = $shift->status->dispatchIdleAccentClasses();
            @endphp
            <div
                wire:key="idle-shift-{{ $shift->id }}"
                class="rounded-xl border px-2.5 py-2.5 text-sm {{ $accent['row'] }}"
            >
                <div class="flex items-center justify-between gap-2">
                    <div class="flex min-w-0 items-center gap-2">
                        <flux:icon.truck class="size-4 {{ $accent['icon'] }}" />
                        <span class="truncate font-semibold text-slate-900 dark:text-slate-50">{{ $shift->vehicle?->prefix ?? __('Sem prefixo') }}</span>
                    </div>
                    <span class="shrink-0 rounded-full px-2 py-0.5 text-[11px] font-semibold {{ $accent['badge'] }}">
                        {{ $shift->status->label() }}
                    </span>
                </div>
                <span class="mt-1 block text-xs text-slate-500">
                    {{ $shift->municipio?->razao_social ?? ('#'.$shift->municipio_id) }}
                    · {{ __('Turno') }} #{{ $shift->id }}
                </span>
            </div>
        @endforeach

        @if ($idleTotal === 0)
            <flux:text size="sm" class="py-4 text-center text-slate-500">
                {{ __('Nenhuma viatura sem turno ou em estado operacional indisponível.') }}
            </flux:text>
        @endif
    </div>
</flux:card>
