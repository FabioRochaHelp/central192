<div class="flex items-center gap-2 px-1 py-1.5 text-start text-sm">
    <flux:avatar
        :name="auth()->user()->name"
        :initials="auth()->user()->initials()"
    />
    <div class="grid flex-1 text-start text-sm leading-tight">
        <flux:heading class="truncate">{{ auth()->user()->name }}</flux:heading>
        <flux:text class="truncate">{{ auth()->user()->email }}</flux:text>
    </div>
</div>

<flux:menu.separator />

<flux:menu.heading>{{ __('Tema') }}</flux:menu.heading>
<flux:menu.radio.group x-model="$flux.appearance">
    <flux:menu.radio value="light" icon="sun">{{ __('Claro') }}</flux:menu.radio>
    <flux:menu.radio value="dark" icon="moon">{{ __('Escuro') }}</flux:menu.radio>
    <flux:menu.radio value="system" icon="computer-desktop">{{ __('Sistema') }}</flux:menu.radio>
</flux:menu.radio.group>

@if (auth()->user()?->legacyProfile() === \App\Domain\Operations\Enums\UserLegacyProfile::Dispatcher)
    <flux:menu.separator />
    <flux:menu.heading>{{ __('Chamadas') }}</flux:menu.heading>
    <livewire:dispatcher-call-intake-toggle />
@endif

<flux:menu.separator />

<flux:menu.item :href="route('profile.edit')" icon="user" wire:navigate>
    {{ __('Meu perfil') }}
</flux:menu.item>
<flux:menu.item :href="route('security.edit')" icon="shield-check" wire:navigate>
    {{ __('Segurança') }}
</flux:menu.item>

<flux:menu.separator />

<form method="POST" action="{{ route('screen-lock.store') }}" class="w-full">
    @csrf
    <flux:menu.item
        as="button"
        type="submit"
        icon="lock-closed"
        class="w-full cursor-pointer"
        data-test="lock-screen-button"
    >
        {{ __('Bloquear tela') }}
    </flux:menu.item>
</form>

<flux:menu.separator />

<form method="POST" action="{{ route('logout') }}" class="w-full">
    @csrf
    <flux:menu.item
        as="button"
        type="submit"
        icon="arrow-right-start-on-rectangle"
        class="w-full cursor-pointer"
        data-test="logout-button"
    >
        {{ __('Sair') }}
    </flux:menu.item>
</form>
