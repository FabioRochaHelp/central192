@props(['tone' => 'blue', 'subtitle' => null])

@php
    $chip = match ($tone) {
        'orange' => 'bg-orange-50 text-orange-600 ring-orange-100 dark:bg-orange-900/25 dark:text-orange-400 dark:ring-orange-900/40',
        default => 'bg-blue-50 text-blue-700 ring-blue-100 dark:bg-blue-900/30 dark:text-blue-300 dark:ring-blue-900/40',
    };
@endphp

<div {{ $attributes->merge(['class' => 'flex items-center gap-3']) }}>
    @isset($icon)
        <span class="flex size-9 shrink-0 items-center justify-center rounded-xl ring-1 {{ $chip }}">
            {{ $icon }}
        </span>
    @endisset
    <div class="min-w-0">
        <flux:heading size="lg" class="text-slate-800 dark:text-slate-100">{{ $slot }}</flux:heading>
        @if ($subtitle)
            <flux:text size="sm" class="text-zinc-500 dark:text-zinc-400">{{ $subtitle }}</flux:text>
        @endif
    </div>
</div>
