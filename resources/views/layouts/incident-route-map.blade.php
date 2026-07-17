<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        @include('partials.head')
    </head>
    <body class="cco-shell antialiased">
        <div class="flex h-screen flex-col overflow-hidden">
            <header class="z-20 flex h-12 shrink-0 items-center justify-between gap-4 border-b border-slate-200/90 bg-white/95 px-4 backdrop-blur-xl dark:border-slate-700/60 dark:bg-slate-950/90">
                <div class="flex items-center gap-3">
                    <x-app-logo :sidebar="false" href="{{ route('operations.incidents.show', $incident) }}" />
                    <span class="hidden h-4 w-px bg-slate-200 dark:bg-slate-700 sm:block"></span>
                    <div class="hidden items-center gap-1.5 sm:flex">
                        <flux:icon.map class="size-3.5 text-blue-600 dark:text-blue-400" />
                        <span class="text-sm font-semibold tracking-tight text-slate-800 dark:text-slate-100">{{ __('Percurso da viatura') }}</span>
                    </div>
                </div>

                <div class="flex items-center gap-2">
                    <flux:button href="{{ route('operations.incidents.show', $incident) }}" icon="arrow-left" size="xs" variant="ghost">
                        {{ __('Ocorrência') }}
                    </flux:button>
                    <flux:button as="button" type="button" icon="x-mark" size="xs" variant="ghost" x-data x-on:click="window.close()">
                        {{ __('Fechar') }}
                    </flux:button>
                </div>
            </header>

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
