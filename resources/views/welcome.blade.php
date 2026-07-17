<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        @include('partials.head')
    </head>
    <body class="cco-shell antialiased">

        @auth
            <script>window.location.href = "{{ route('dashboard') }}";</script>
        @endauth

        <div class="flex min-h-screen flex-col lg:grid lg:grid-cols-2">

            {{-- ── Coluna esquerda: identidade institucional ───────────────────── --}}
            <aside class="relative flex flex-col justify-between overflow-hidden px-8 py-10 lg:px-12 lg:py-14" style="background-color: #ea580c;">

                {{-- Elementos decorativos geométricos --}}
                <div class="pointer-events-none absolute inset-0 overflow-hidden" aria-hidden="true">
                    {{-- Triângulo Defesa Civil — grande, fundo --}}
                    <svg class="absolute -bottom-16 -right-16 opacity-[0.07]" width="460" height="400" viewBox="0 0 460 400" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <polygon points="230,0 460,400 0,400" fill="white"/>
                    </svg>
                    {{-- Triângulo menor, topo esquerdo --}}
                    <svg class="absolute -top-10 -left-10 opacity-[0.06]" width="240" height="210" viewBox="0 0 240 210" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <polygon points="120,0 240,210 0,210" fill="white"/>
                    </svg>
                    {{-- Linha diagonal sutil --}}
                    <div class="absolute top-0 right-24 h-full w-px bg-white/10"></div>
                </div>

                {{-- Cabeçalho: logo + nome --}}
                <div class="relative z-10">
                    <div class="flex items-center gap-3">
                        <div class="flex h-11 w-11 items-center justify-center rounded-xl bg-gradient-to-br from-blue-600 to-blue-900 shadow-lg shadow-blue-900/40">
                            <x-app-logo-icon class="size-6 fill-current text-white" />
                        </div>
                        <span class="text-xl font-bold tracking-tight text-white">{{ config('app.name', 'CCO') }}</span>
                    </div>
                </div>

                {{-- Conteúdo central --}}
                <div class="relative z-10 my-10 space-y-8">
                    <div class="space-y-3">
                        <p class="text-xs font-semibold uppercase tracking-widest text-white/70">
                            {{ __('Sistema de Gestão Operacional') }}
                        </p>
                        <h1 class="text-3xl font-bold leading-tight tracking-tight text-white lg:text-4xl">
                            {{ __('Centro de Controle de Operações') }}
                        </h1>
                        <p class="text-base leading-relaxed text-white/75">
                            {{ __('Plataforma integrada para coordenação de emergências, rastreamento de viaturas e gestão de ocorrências em tempo real.') }}
                        </p>
                    </div>

                    {{-- Destaques --}}
                    <div class="space-y-4">
                        <div class="flex items-start gap-3">
                            <div class="mt-0.5 flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-black/20 ring-1 ring-white/25">
                                <flux:icon.radio class="size-4 text-white" />
                            </div>
                            <div>
                                <p class="text-sm font-semibold text-white">{{ __('Despacho em tempo real') }}</p>
                                <p class="text-sm text-white/70">{{ __('Gerencie filas, turnos e empenho de viaturas com atualização contínua.') }}</p>
                            </div>
                        </div>

                        <div class="flex items-start gap-3">
                            <div class="mt-0.5 flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-black/20 ring-1 ring-white/25">
                                <flux:icon.map class="size-4 text-white" />
                            </div>
                            <div>
                                <p class="text-sm font-semibold text-white">{{ __('Rastreamento de viaturas') }}</p>
                                <p class="text-sm text-white/70">{{ __('Mapa tático com posições e status de todas as unidades operacionais.') }}</p>
                            </div>
                        </div>

                        <div class="flex items-start gap-3">
                            <div class="mt-0.5 flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-black/20 ring-1 ring-white/25">
                                <flux:icon.rectangle-stack class="size-4 text-white" />
                            </div>
                            <div>
                                <p class="text-sm font-semibold text-white">{{ __('Gestão de ocorrências') }}</p>
                                <p class="text-sm text-white/70">{{ __('Registro, kanban operacional e relatórios por tipo e modalidade de atendimento.') }}</p>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Rodapé da coluna --}}
                <div class="relative z-10">
                    <p class="text-xs text-white/50">
                        &copy; {{ date('Y') }} {{ config('app.name', 'CCO') }}. {{ __('Todos os direitos reservados.') }}
                    </p>
                </div>
            </aside>

            {{-- ── Coluna direita: formulário de login ─────────────────────────── --}}
            <main class="flex items-center justify-center bg-white px-8 py-12 dark:bg-slate-950">
                <div class="w-full max-w-sm space-y-7">

                    {{-- Logo móvel (visível apenas em telas pequenas, onde a aside some) --}}
                    <div class="flex items-center gap-3 lg:hidden">
                        <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-gradient-to-br from-blue-600 to-blue-900 shadow shadow-blue-900/30">
                            <x-app-logo-icon class="size-5 fill-current text-white" />
                        </div>
                        <span class="text-lg font-bold tracking-tight text-slate-900 dark:text-white">{{ config('app.name', 'CCO') }}</span>
                    </div>

                    {{-- Cabeçalho do form --}}
                    <div class="space-y-1">
                        <h2 class="text-2xl font-bold tracking-tight text-slate-900 dark:text-slate-50">
                            {{ __('Acesse o sistema') }}
                        </h2>
                        <p class="text-sm text-slate-500 dark:text-slate-400">
                            {{ __('Informe suas credenciais para continuar.') }}
                        </p>
                    </div>

                    {{-- Status de sessão (ex: após redefinição de senha) --}}
                    <x-auth-session-status :status="session('status')" />

                    {{-- Formulário --}}
                    <form method="POST" action="{{ route('login.store') }}" class="space-y-5">
                        @csrf

                        <flux:input
                            name="email"
                            :label="__('E-mail')"
                            :value="old('email')"
                            type="email"
                            required
                            autofocus
                            autocomplete="email"
                            placeholder="voce@exemplo.com.br"
                            :invalid="$errors->has('email')"
                        />
                        @error('email')
                            <p class="-mt-3 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>
                        @enderror

                        <div class="relative">
                            <flux:input
                                name="password"
                                :label="__('Senha')"
                                type="password"
                                required
                                autocomplete="current-password"
                                :placeholder="__('Sua senha')"
                                viewable
                                :invalid="$errors->has('password')"
                            />
                            @if (Route::has('password.request'))
                                <flux:link class="absolute top-0 end-0 text-sm" :href="route('password.request')">
                                    {{ __('Esqueceu a senha?') }}
                                </flux:link>
                            @endif
                        </div>
                        @error('password')
                            <p class="-mt-3 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>
                        @enderror

                        <flux:checkbox
                            name="remember"
                            :label="__('Manter conectado')"
                            :checked="old('remember')"
                        />

                        <x-turnstile />

                        <flux:button variant="primary" type="submit" class="w-full">
                            {{ __('Entrar') }}
                        </flux:button>
                    </form>
                </div>
            </main>

        </div>

        @fluxScripts
    </body>
</html>
