@if ($callAlertReport)
    <flux:card class="border-s-4 border-s-amber-500 dark:border-s-amber-400">
        <flux:subheading>{{ __('Alerta operacional — comportamento do fogo') }}</flux:subheading>
        <flux:text size="sm" class="mt-2 text-zinc-600 dark:text-zinc-400">
            {{ __(':count alerta(s) vinculado(s) a esta ocorrência', ['count' => $callAlertReport['alert_count']]) }}
            @if ($callAlertReport['period']['from'] && $callAlertReport['period']['to'])
                · {{ $callAlertReport['period']['from'] }} – {{ $callAlertReport['period']['to'] }}
            @endif
        </flux:text>

        @if ($callAlertReport['metrics'] !== [])
            <div class="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($callAlertReport['metrics'] as $metric)
                    <div class="rounded-lg border border-zinc-200 px-3 py-2 dark:border-zinc-700">
                        <flux:text size="sm" class="text-zinc-500">{{ $metric['label'] }}</flux:text>
                        <flux:text class="font-medium">{{ $metric['value'] }}</flux:text>
                        @if ($metric['hint'])
                            <flux:text size="xs" class="text-zinc-500">{{ $metric['hint'] }}</flux:text>
                        @endif
                    </div>
                @endforeach
            </div>
        @endif

        <div class="mt-4 flex flex-wrap items-center gap-2">
            <flux:badge color="{{ match ($callAlertReport['risk']['level']) {
                'critical' => 'red',
                'high' => 'orange',
                'moderate' => 'amber',
                default => 'zinc',
            } }}">
                {{ __('Risco') }}: {{ $callAlertReport['risk']['label'] }} ({{ $callAlertReport['risk']['score'] }})
            </flux:badge>
            @if ($callAlertReport['reference'])
                <flux:text size="sm" class="text-zinc-500">{{ __('Ref.') }} {{ $callAlertReport['reference'] }}</flux:text>
            @endif
        </div>

        @if ($callAlertReport['observations'] !== [])
            <ul class="mt-4 space-y-2">
                @foreach ($callAlertReport['observations'] as $observation)
                    <li class="rounded-lg border border-zinc-200 px-3 py-2 text-sm dark:border-zinc-700">
                        <span class="font-medium">{{ $observation['title'] }}</span>
                        <span class="text-zinc-600 dark:text-zinc-400"> — {{ $observation['text'] }}</span>
                    </li>
                @endforeach
            </ul>
        @endif
    </flux:card>
@endif
