<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        @include('partials.head')
    </head>
    <body
        class="cco-shell min-h-screen antialiased lg:h-dvh lg:max-h-dvh lg:overflow-hidden"
        @auth
            data-broadcast-operations="{{ auth()->user()?->hasOperationalAbility('dispatch.view') ? '1' : '0' }}"
            data-broadcast-call-intake="{{ auth()->user()?->receivesOperationalCallIntakeBroadcast() ? '1' : '0' }}"
        @endauth
    >
        @auth
            @if (auth()->user()?->shouldMountOperationalCallIntakeBridge())
                <livewire:operations.operational-call-intake-bridge />
            @endif
            @if (auth()->user()?->hasOperationalAbility('dispatch.view'))
                <livewire:operations.call-alert-fire-report-modal />
            @endif
        @endauth
        <flux:sidebar sticky :collapsible="true" class="cco-sidebar flex max-h-dvh flex-col overflow-hidden border-e border-slate-200/90 bg-white shadow-sm shadow-slate-900/5 dark:border-zinc-800/80 dark:bg-zinc-950 dark:shadow-none">
            {{-- Faixa branca no topo sobre fundo laranja --}}
            <div class="absolute top-0 left-0 right-0 h-[3px] bg-white/30 z-50 pointer-events-none" aria-hidden="true"></div>

            <flux:sidebar.header class="shrink-0">
                {{-- O próprio logo é o botão de recolher/expandir o menu. --}}
                <ui-sidebar-toggle
                    data-flux-sidebar-collapse
                    class="block w-full cursor-pointer"
                    title="{{ __('Recolher ou expandir o menu') }}"
                >
                    {{-- Expandido: placa branca com o lockup horizontal --}}
                    <span class="flex w-full items-center rounded-lg bg-white px-3 py-2.5 shadow-sm ring-1 ring-black/5 transition hover:ring-2 hover:ring-black/10 in-data-flux-sidebar-collapsed-desktop:hidden">
                        <img
                            src="{{ asset('img/logo_cco_horizontal.png') }}"
                            alt="{{ config('app.name', 'CCO') }}"
                            class="h-auto w-full"
                        />
                    </span>
                    {{-- Recolhido: placa quadrada com a marca --}}
                    <span class="mx-auto hidden aspect-square size-9 items-center justify-center rounded-lg bg-white shadow-sm ring-1 ring-black/5 transition hover:ring-2 hover:ring-black/10 in-data-flux-sidebar-collapsed-desktop:flex">
                        <x-app-logo-icon class="size-6 text-[#0a1b70]" />
                    </span>
                </ui-sidebar-toggle>
            </flux:sidebar.header>

            <div class="cco-sidebar-nav-scroll min-h-0 flex-1 overflow-x-hidden overflow-y-auto overscroll-contain pe-0.5">
                <flux:sidebar.nav>
                    <flux:sidebar.group
                        expandable
                        :expanded="request()->routeIs('dashboard')"
                        icon="home"
                        :heading="__('Platform')"
                        class="grid"
                    >
                        <flux:sidebar.item icon="home" :href="route('dashboard')" :current="request()->routeIs('dashboard')" wire:navigate>
                            {{ __('Dashboard') }}
                        </flux:sidebar.item>
                    </flux:sidebar.group>

                    @if (auth()->user()?->canViewOperationalIncidents() || auth()->user()?->hasOperationalAbility('incident.create'))
                    <flux:sidebar.group
                        expandable
                        :expanded="request()->routeIs('operations.dispatch') || request()->routeIs('operations.incidents.*') || request()->routeIs('operations.fleet')"
                        icon="radio"
                        :heading="__('Operações')"
                        class="grid"
                    >
                        @if (auth()->user()?->hasOperationalAbility('dispatch.view'))
                            <flux:sidebar.item
                                icon="radio"
                                :href="route('operations.dispatch')"
                                :current="request()->routeIs('operations.dispatch')"
                                wire:navigate
                            >
                                {{ __('CCO') }}
                            </flux:sidebar.item>
                        @endif
                        @if (auth()->user()?->canViewOperationalIncidents())
                            <flux:sidebar.item
                                icon="rectangle-stack"
                                :href="route('operations.incidents.index')"
                                :current="request()->routeIs('operations.incidents.index') || request()->routeIs('operations.incidents.show') || request()->routeIs('operations.incidents.nurse-report') || request()->routeIs('operations.incidents.victims.*')"
                                wire:navigate
                            >
                                {{ __('Ocorrências') }}
                            </flux:sidebar.item>
                        @endif
                        @if (auth()->user()?->hasOperationalAbility('incident.create'))
                            <flux:sidebar.item
                                icon="plus-circle"
                                :href="route('operations.incidents.start')"
                                :current="request()->routeIs('operations.incidents.start') || request()->routeIs('operations.incidents.create')"
                                wire:navigate
                            >
                                {{ __('Nova ocorrência') }}
                            </flux:sidebar.item>
                        @endif
                        @if (auth()->user()?->hasOperationalAbility('dispatch.view'))
                            <flux:sidebar.item
                                icon="truck"
                                :href="route('operations.fleet')"
                                :current="request()->routeIs('operations.fleet')"
                                wire:navigate
                            >
                                {{ __('Turnos e viaturas') }}
                            </flux:sidebar.item>
                        @endif
                    </flux:sidebar.group>
                    @endif

                @if (auth()->user()?->hasOperationalAbility('catalog.manage'))
                    <flux:sidebar.group
                        expandable
                        :expanded="request()->routeIs('operations.cadastro.*') || request()->routeIs('operations.catalog.*')"
                        icon="folder-open"
                        :heading="__('Cadastro')"
                        class="grid"
                    >
                        @if (auth()->user()?->isOperationalCentral())
                            <flux:sidebar.item
                                icon="building-office-2"
                                :href="route('operations.cadastro.bases')"
                                :current="request()->routeIs('operations.cadastro.bases')"
                                wire:navigate
                            >
                                {{ __('Bases') }}
                            </flux:sidebar.item>
                        @endif
                        <flux:sidebar.item
                            icon="cube"
                            :href="route('operations.cadastro.vehicles')"
                            :current="request()->routeIs('operations.cadastro.vehicles') || request()->routeIs('operations.catalog.vehicles')"
                            wire:navigate
                        >
                            {{ __('Viaturas') }}
                        </flux:sidebar.item>
                        <flux:sidebar.item
                            icon="users"
                            :href="route('operations.cadastro.staff')"
                            :current="request()->routeIs('operations.cadastro.staff') || request()->routeIs('operations.catalog.staff')"
                            wire:navigate
                        >
                            {{ __('Efetivo') }}
                        </flux:sidebar.item>
                        @can('viewAny', \App\Models\Shift::class)
                            <flux:sidebar.item
                                icon="clock"
                                :href="route('operations.cadastro.shifts')"
                                :current="request()->routeIs('operations.cadastro.shifts')"
                                wire:navigate
                            >
                                {{ __('Turnos de serviço') }}
                            </flux:sidebar.item>
                        @endcan
                    </flux:sidebar.group>
                @endif

                @can('viewAny', \App\Models\User::class)
                    <flux:sidebar.group
                        expandable
                        :expanded="request()->routeIs('operations.admin.*')"
                        icon="shield-check"
                        :heading="__('Administração')"
                        class="grid"
                    >
                        <flux:sidebar.item
                            icon="users"
                            :href="route('operations.admin.users')"
                            :current="request()->routeIs('operations.admin.users')"
                            wire:navigate
                        >
                            {{ __('Usuários') }}
                        </flux:sidebar.item>
                    </flux:sidebar.group>
                @endcan

                @if (auth()->user()?->isOperationalCentral())
                    <flux:sidebar.group
                        expandable
                        :expanded="request()->routeIs('operations.reports.*')"
                        icon="chart-bar"
                        :heading="__('Relatórios')"
                        class="grid"
                    >
                        <flux:sidebar.item
                            icon="rectangle-stack"
                            :href="route('operations.reports.incidents.index')"
                            :current="request()->routeIs('operations.reports.incidents.*')"
                            wire:navigate
                        >
                            {{ __('Ocorrências') }}
                        </flux:sidebar.item>
                        <flux:sidebar.item
                            icon="map-pin"
                            :href="route('operations.reports.focos.index')"
                            :current="request()->routeIs('operations.reports.focos.*')"
                            wire:navigate
                        >
                            {{ __('Focos de calor') }}
                        </flux:sidebar.item>
                        <flux:sidebar.item
                            icon="fire"
                            :href="route('operations.reports.fire-scars.index')"
                            :current="request()->routeIs('operations.reports.fire-scars.*')"
                            wire:navigate
                        >
                            {{ __('Cicatrizes de incêndio') }}
                        </flux:sidebar.item>
                    </flux:sidebar.group>

                    <flux:sidebar.group
                        expandable
                        :expanded="request()->routeIs('operations.parameters.*')"
                        icon="adjustments-horizontal"
                        :heading="__('Parâmetros')"
                        class="grid"
                    >
                        <flux:sidebar.item
                            icon="wrench-screwdriver"
                            :href="route('operations.parameters.accessories')"
                            :current="request()->routeIs('operations.parameters.accessories')"
                            wire:navigate
                        >
                            {{ __('Acessório') }}
                        </flux:sidebar.item>
                        <flux:sidebar.item
                            icon="lifebuoy"
                            :href="route('operations.parameters.operational-supports')"
                            :current="request()->routeIs('operations.parameters.operational-supports')"
                            wire:navigate
                        >
                            {{ __('Apoio') }}
                        </flux:sidebar.item>
                        <flux:sidebar.item
                            icon="map-pin"
                            :href="route('operations.parameters.care-locals')"
                            :current="request()->routeIs('operations.parameters.care-locals')"
                            wire:navigate
                        >
                            {{ __('Local') }}
                        </flux:sidebar.item>
                        <flux:sidebar.item
                            icon="heart"
                            :href="route('operations.parameters.injury-sites')"
                            :current="request()->routeIs('operations.parameters.injury-sites')"
                            wire:navigate
                        >
                            {{ __('Local de Ferimento') }}
                        </flux:sidebar.item>
                        <flux:sidebar.item
                            icon="tag"
                            :href="route('operations.parameters.natures')"
                            :current="request()->routeIs('operations.parameters.natures')"
                            wire:navigate
                        >
                            {{ __('Natureza') }}
                        </flux:sidebar.item>
                        <flux:sidebar.item
                            icon="beaker"
                            :href="route('operations.parameters.procedures')"
                            :current="request()->routeIs('operations.parameters.procedures')"
                            wire:navigate
                        >
                            {{ __('Procedimento') }}
                        </flux:sidebar.item>
                        <flux:sidebar.item
                            icon="user"
                            :href="route('operations.parameters.victim-types')"
                            :current="request()->routeIs('operations.parameters.victim-types')"
                            wire:navigate
                        >
                            {{ __('Tipo Vitima') }}
                        </flux:sidebar.item>
                        <flux:sidebar.item
                            icon="building-office-2"
                            :href="route('operations.parameters.health-units')"
                            :current="request()->routeIs('operations.parameters.health-units')"
                            wire:navigate
                        >
                            {{ __('Unidades de Atendimento') }}
                        </flux:sidebar.item>
                        <flux:sidebar.item
                            icon="archive-box"
                            :href="route('operations.parameters.checklist-resources')"
                            :current="request()->routeIs('operations.parameters.checklist-resources')"
                            wire:navigate
                        >
                            {{ __('Recursos do check-list') }}
                        </flux:sidebar.item>
                    </flux:sidebar.group>
                @endif
                </flux:sidebar.nav>
            </div>

            <div class="cco-sidebar-footer shrink-0 border-t border-white/15 pt-3 dark:border-white/10">
            @auth
                <x-desktop-user-menu class="hidden lg:block" :name="auth()->user()->name" />
            @endauth
            </div>
        </flux:sidebar>

        <!-- Mobile User Menu -->
        <flux:header class="sticky top-0 z-30 border-b border-slate-200/90 bg-white/95 backdrop-blur-xl dark:border-blue-500/10 dark:bg-slate-950/90 lg:hidden">
            <flux:sidebar.toggle class="lg:hidden" icon="bars-2" inset="left" />

            <flux:spacer />

            @auth
                <x-mobile-user-menu />
            @else
                <flux:dropdown position="top" align="end">
                    <flux:button variant="ghost" size="sm" icon="paint-brush" inset="top bottom" />
                    <flux:menu>
                        <flux:menu.heading>{{ __('Tema') }}</flux:menu.heading>
                        <flux:menu.radio.group x-model="$flux.appearance">
                            <flux:menu.radio value="light" icon="sun">{{ __('Claro') }}</flux:menu.radio>
                            <flux:menu.radio value="dark" icon="moon">{{ __('Escuro') }}</flux:menu.radio>
                            <flux:menu.radio value="system" icon="computer-desktop">{{ __('Sistema') }}</flux:menu.radio>
                        </flux:menu.radio.group>
                    </flux:menu>
                </flux:dropdown>
            @endauth
        </flux:header>

        {{ $slot }}

        @persist('toast')
            <flux:toast.group>
                <flux:toast />
            </flux:toast.group>
        @endpersist

        @fluxScripts

        <x-screen-lock-idle-monitor />
    </body>
</html>
