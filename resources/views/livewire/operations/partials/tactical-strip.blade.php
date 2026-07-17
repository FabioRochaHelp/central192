@props(['stats'])

<div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-5">
    <div class="cco-stat-card">
        <flux:text size="sm" class="text-slate-600 dark:text-slate-400">{{ __('Ocorrências abertas') }}</flux:text>
        <flux:heading size="xl" class="cco-stat-value">{{ $stats['open_incidents'] }}</flux:heading>
    </div>
    <div class="cco-stat-card">
        <flux:text size="sm" class="text-slate-600 dark:text-slate-400">{{ __('Cartões no Kanban') }}</flux:text>
        <flux:heading size="xl" class="cco-stat-value">{{ $stats['active_dispatches'] }}</flux:heading>
    </div>
    <div class="cco-stat-card">
        <flux:text size="sm" class="text-slate-600 dark:text-slate-400">{{ __('Turnos disponíveis') }}</flux:text>
        <flux:heading size="xl" class="cco-stat-value">{{ $stats['available_units'] }}</flux:heading>
    </div>
    <div class="cco-stat-card">
        <flux:text size="sm" class="text-slate-600 dark:text-slate-400">{{ __('Viaturas sem turno') }}</flux:text>
        <flux:heading size="xl" class="cco-stat-value">{{ $stats['idle_vehicles'] }}</flux:heading>
    </div>
    <a
        href="{{ route('operations.call-alerts.index') }}"
        wire:navigate
        class="cco-stat-card block transition-colors hover:border-amber-500/50 dark:hover:border-amber-500/50"
    >
        <flux:text size="sm" class="text-slate-600 dark:text-slate-400">{{ __('Alertas pendentes') }}</flux:text>
        <flux:heading size="xl" class="cco-stat-value text-amber-600 dark:text-amber-400">{{ $stats['pending_call_alerts'] }}</flux:heading>
    </a>
</div>
