<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        @include('partials.head')
    </head>
    <body class="cco-shell antialiased" @auth data-broadcast-operations="{{ auth()->user()?->hasOperationalAbility('dispatch.view') ? '1' : '0' }}" data-broadcast-call-intake="{{ auth()->user()?->receivesOperationalCallIntakeBroadcast() ? '1' : '0' }}" @endauth>
        @auth
            @if (auth()->user()?->shouldMountOperationalCallIntakeBridge())
                <livewire:operations.operational-call-intake-bridge />
            @endif
            @if (auth()->user()?->hasOperationalAbility('dispatch.view'))
                <livewire:operations.call-alert-fire-report-modal />
            @endif
        @endauth

        <div class="flex h-screen flex-col overflow-hidden">
            {{-- Barra de topo compacta --}}
            <header class="z-20 flex h-12 shrink-0 items-center justify-between gap-4 border-b border-slate-200/90 bg-white/95 px-4 backdrop-blur-xl dark:border-slate-700/60 dark:bg-slate-950/90">
                <div class="flex items-center gap-3">
                    <x-app-logo :sidebar="false" href="{{ route('dashboard') }}" />
                    <span class="hidden h-4 w-px bg-slate-200 dark:bg-slate-700 sm:block"></span>
                    <div class="hidden items-center gap-1.5 sm:flex">
                        <flux:icon.map class="size-3.5 text-blue-700 dark:text-blue-400" />
                        <span class="text-sm font-semibold tracking-tight text-slate-800 dark:text-slate-100">{{ __('Mapa tático') }}</span>
                    </div>
                    <span class="inline-flex items-center gap-1.5 rounded-full border border-emerald-600/30 bg-emerald-500/10 px-2.5 py-0.5 text-xs font-semibold uppercase tracking-wide text-emerald-700 dark:border-emerald-500/30 dark:text-emerald-300">
                        <span class="relative flex h-1.5 w-1.5">
                            <span class="absolute inline-flex h-full w-full animate-ping rounded-full bg-emerald-500 opacity-60"></span>
                            <span class="relative inline-flex h-1.5 w-1.5 rounded-full bg-emerald-500"></span>
                        </span>
                        {{ __('Ao vivo') }}
                    </span>
                </div>

                <div class="flex items-center gap-2">
                    <flux:button href="{{ route('operations.dispatch') }}" icon="arrow-left" size="xs" variant="ghost">
                        {{ __('Despacho') }}
                    </flux:button>
                    <flux:button as="button" type="button" icon="x-mark" size="xs" variant="ghost" x-data x-on:click="window.close()">
                        {{ __('Fechar') }}
                    </flux:button>
                </div>
            </header>

            {{-- Área principal — ocupa todo o restante da tela --}}
            <main class="relative" style="height: calc(100vh - 3rem)">
                {{ $slot }}
            </main>
        </div>

        @persist('toast')
            <flux:toast.group>
                <flux:toast />
            </flux:toast.group>
        @endpersist

        @fluxScripts
    </body>
</html>
