<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        @include('partials.head')
    </head>
    <body class="min-h-screen bg-white antialiased">
        <div class="grid min-h-dvh lg:grid-cols-2">
            {{-- ── Esquerda: fundo branco com o logo CCO ───────────────────────── --}}
            <div class="hidden items-center justify-center bg-white p-10 lg:flex">
                <a href="{{ route('home') }}" wire:navigate class="block w-full max-w-md">
                    <img
                        src="{{ asset('img/logo-cco.png') }}"
                        alt="{{ __('CCO — Centro de Controle Operacional · Vale do Paranapanema') }}"
                        class="mx-auto w-full max-w-md"
                    />
                </a>
            </div>

            {{-- ── Direita: fundo laranja da marca com o formulário ─────────────── --}}
            <div class="flex min-h-dvh items-center justify-center bg-[#f6600f] p-6 sm:p-10">
                <div class="w-full max-w-md rounded-2xl bg-white p-8 shadow-2xl shadow-black/20 sm:p-10 dark:bg-zinc-900">
                    {{-- Logo compacto só no mobile (a coluna esquerda fica oculta) --}}
                    <a href="{{ route('home') }}" wire:navigate class="mb-8 flex justify-center lg:hidden">
                        <span class="rounded-xl bg-white p-3">
                            <img
                                src="{{ asset('img/logo-cco.png') }}"
                                alt="{{ __('CCO — Centro de Controle Operacional') }}"
                                class="h-20 w-auto"
                            />
                        </span>
                    </a>

                    {{ $slot }}
                </div>
            </div>
        </div>

        @persist('toast')
            <flux:toast.group>
                <flux:toast />
            </flux:toast.group>
        @endpersist

        @fluxScripts
    </body>
</html>
