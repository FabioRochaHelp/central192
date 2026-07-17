<x-layouts::app :title="__('Ocorrência registrada')">
    <div class="cco-page-gap mx-auto max-w-lg">
        <flux:heading size="xl">{{ __('Ocorrência registrada') }}</flux:heading>
        @php
            /** @var array{talao: string|int, dispatch_year: int|string}|null $info */
            $info = session('registered_incident');
        @endphp
        @if (is_array($info))
            <flux:callout variant="success" class="mt-4">
                <flux:text>
                    {{ __('Talão') }}:
                    <span class="font-semibold tabular-nums">{{ $info['dispatch_year'] }}/{{ $info['talao'] }}</span>
                </flux:text>
            </flux:callout>
        @else
            <flux:text class="mt-2">{{ __('O cadastro foi concluído.') }}</flux:text>
        @endif
        <flux:text class="mt-4 text-sm text-zinc-600 dark:text-zinc-400">
            {{ __('Você pode fechar esta página.') }}
        </flux:text>
    </div>
</x-layouts::app>
