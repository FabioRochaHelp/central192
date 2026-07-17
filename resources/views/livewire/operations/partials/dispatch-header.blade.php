<div class="flex flex-col gap-4 md:flex-row md:items-end md:justify-between">
    <div class="space-y-3">
        <div class="flex flex-wrap items-center gap-2">
            <span
                class="inline-flex items-center gap-2 rounded-full border border-emerald-600/35 bg-emerald-500/15 px-3 py-1 text-xs font-semibold uppercase tracking-wide text-emerald-800 dark:border-emerald-500/30 dark:bg-emerald-500/10 dark:text-emerald-300"
            >
                <span class="relative flex h-2 w-2">
                    <span class="absolute inline-flex h-full w-full animate-ping rounded-full bg-emerald-500 opacity-50 dark:bg-emerald-400"></span>
                    <span
                        class="relative inline-flex h-2 w-2 rounded-full bg-emerald-500 shadow-[0_0_8px_rgba(16,185,129,0.85)] dark:bg-emerald-400 dark:shadow-[0_0_8px_rgba(52,211,153,0.9)]"
                    ></span>
                </span>
                {{ __('Ao vivo') }}
            </span>
            <span
                class="rounded-full border border-slate-300/90 bg-white/80 px-3 py-1 text-xs font-medium text-slate-600 shadow-sm dark:border-slate-600/60 dark:bg-slate-900/50 dark:text-slate-400 dark:shadow-none"
            >
                {{ __('Canal operacional') }}
            </span>
        </div>
        <div>
            <flux:heading size="xl" class="tracking-tight text-slate-900 dark:text-slate-50">{{ __('Centro de Controle de Operações (CCO)') }}</flux:heading>
        </div>
    </div>

    @auth
        @if (auth()->user()->hasMultiMunicipioAccess())
            <div
                class="w-full max-w-md rounded-xl border border-slate-200/95 bg-white/85 p-4 shadow-md shadow-slate-900/5 backdrop-blur-sm dark:border-slate-700/50 dark:bg-slate-900/40 dark:shadow-inner dark:shadow-black/20"
            >
                <flux:select wire:model.live="selectedOperationalMunicipioId" :label="__('Base | Município')" placeholder="{{ __('Selecionar escopo') }}">
                    <flux:select.option value="">{{ __('Visão supervisor (todas as bases)') }}</flux:select.option>
                    @foreach ($municipioOptions as $municipio)
                        <flux:select.option value="{{ $municipio->id }}">{{ $municipio->razao_social }}</flux:select.option>
                    @endforeach
                </flux:select>
            </div>
        @endif
    @endauth
</div>
