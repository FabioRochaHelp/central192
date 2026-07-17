<x-layouts::auth :title="__('Área segura')">
    <div class="flex flex-col gap-6">
        <div class="flex flex-col items-center gap-4 text-center">
            <flux:avatar
                :name="auth()->user()->name"
                :initials="auth()->user()->initials()"
                size="lg"
            />
            <x-auth-header
                :title="__('Área segura')"
                :description="__('A sessão está bloqueada. Informe sua senha para continuar.')"
            />
        </div>

        <form method="POST" action="{{ route('screen-lock.unlock') }}" class="flex flex-col gap-6">
            @csrf

            <flux:input
                name="password"
                :label="__('Senha')"
                type="password"
                required
                autofocus
                autocomplete="current-password"
                :placeholder="__('Sua senha')"
                viewable
                :invalid="$errors->has('password')"
            />
            @error('password')
                <p class="-mt-4 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
            @enderror

            <flux:button variant="primary" type="submit" class="w-full" data-test="unlock-screen-button">
                {{ __('Desbloquear') }}
            </flux:button>
        </form>

        <form method="POST" action="{{ route('logout') }}" class="text-center">
            @csrf
            <flux:button variant="ghost" type="submit" class="w-full" data-test="lock-screen-logout-button">
                {{ __('Sair da conta') }}
            </flux:button>
        </form>
    </div>
</x-layouts::auth>
