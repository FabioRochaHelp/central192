{{-- Tempo real via Reverb (shift.updated). wire:poll longo = fallback se o WebSocket cair. --}}
<div class="cco-page-gap !gap-6" wire:poll.120s>
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <flux:heading size="xl">{{ __('Turnos e viaturas') }}</flux:heading>
            <flux:text class="mt-1">{{ __('Turnos recentes e estado operacional (disponível / empenhado).') }}</flux:text>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            @can('create', \App\Models\Shift::class)
                <flux:button variant="primary" icon="clock" :href="route('operations.cadastro.shifts')" wire:navigate>
                    {{ __('Gerir turnos') }}
                </flux:button>
            @endcan
            <flux:button variant="ghost" icon="radio" :href="route('operations.dispatch')" wire:navigate>
                {{ __('Centro de Controle de Operações') }}
            </flux:button>
        </div>
    </div>

    @if ($message)
        <flux:callout variant="success" icon="check-circle">{{ $message }}</flux:callout>
    @endif

    @error('close')
        <flux:callout variant="danger" icon="x-circle">{{ $message }}</flux:callout>
    @enderror

    <flux:card class="space-y-4">
        @if ($shifts->isEmpty())
            <flux:text>{{ __('Nenhum turno encontrado no período.') }}</flux:text>
        @else
            <div class="overflow-x-auto rounded-xl border border-zinc-200 dark:border-zinc-700">
                <table class="min-w-full divide-y divide-zinc-200 text-start text-sm dark:divide-zinc-700">
                    <thead class="bg-zinc-50 dark:bg-zinc-900/50">
                        <tr>
                            <th class="px-4 py-3 font-medium text-zinc-700 dark:text-zinc-300">{{ __('Turno') }}</th>
                            <th class="px-4 py-3 font-medium text-zinc-700 dark:text-zinc-300">{{ __('Viatura') }}</th>
                            <th class="px-4 py-3 font-medium text-zinc-700 dark:text-zinc-300">{{ __('Estado') }}</th>
                            <th class="px-4 py-3 font-medium text-zinc-700 dark:text-zinc-300">{{ __('Início') }}</th>
                            <th class="px-4 py-3 font-medium text-zinc-700 dark:text-zinc-300">{{ __('Fim previsto') }}</th>
                            <th class="px-4 py-3 font-medium text-zinc-700 dark:text-zinc-300">{{ __('Base') }}</th>
                            <th class="px-4 py-3"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-100 bg-white dark:divide-zinc-800 dark:bg-zinc-900">
                        @foreach ($shifts as $shift)
                            @php
                                $isActive    = $shift->starts_at->isPast() && $shift->ends_at->isFuture();
                                $isEmpenhado = $shift->status === \App\Domain\Operations\Enums\ShiftStatus::Empenhado;
                                $badgeColor  = match ($shift->status) {
                                    \App\Domain\Operations\Enums\ShiftStatus::Disponivel => 'green',
                                    \App\Domain\Operations\Enums\ShiftStatus::Empenhado  => 'yellow',
                                    \App\Domain\Operations\Enums\ShiftStatus::Baixado    => 'zinc',
                                    \App\Domain\Operations\Enums\ShiftStatus::Oficina    => 'blue',
                                    \App\Domain\Operations\Enums\ShiftStatus::Acidente   => 'red',
                                };
                            @endphp
                            <tr wire:key="shift-{{ $shift->id }}" class="hover:bg-zinc-50/80 dark:hover:bg-zinc-800/40">
                                <td class="whitespace-nowrap px-4 py-3 font-mono text-xs text-zinc-500">#{{ $shift->id }}</td>
                                <td class="px-4 py-3 font-medium">{{ $shift->vehicle?->prefix ?? '—' }} · {{ $shift->vehicle?->plate ?? __('Sem placa') }}</td>
                                <td class="px-4 py-3">
                                    <flux:badge size="sm" :inset="true" :color="$badgeColor">{{ $shift->status->label() }}</flux:badge>
                                </td>
                                <td class="whitespace-nowrap px-4 py-3 tabular-nums text-zinc-600 dark:text-zinc-400">{{ $shift->starts_at->format('d/m/Y H:i') }}</td>
                                <td class="whitespace-nowrap px-4 py-3 tabular-nums text-zinc-600 dark:text-zinc-400">
                                    {{ $shift->ends_at->format('d/m/Y H:i') }}
                                    @if ($isActive)
                                        <span class="ml-1 inline-block h-2 w-2 rounded-full bg-emerald-400" title="{{ __('Em andamento') }}"></span>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-xs text-zinc-500">{{ $shift->municipio?->razao_social ?? ('#'.$shift->municipio_id) }}</td>

                                {{-- Ação de encerramento antecipado --}}
                                <td class="whitespace-nowrap px-4 py-3 text-end">
                                    @can('update', $shift)
                                        @if ($isActive)
                                            @if ($isEmpenhado)
                                                <span
                                                    title="{{ __('Viatura em despacho ativo — encerramento bloqueado') }}"
                                                    class="inline-flex cursor-not-allowed items-center gap-1.5 rounded-lg border border-zinc-200 bg-zinc-50 px-2.5 py-1 text-xs font-medium text-zinc-400 dark:border-zinc-700 dark:bg-zinc-800">
                                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                        <path stroke-linecap="round" stroke-linejoin="round" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636"/>
                                                    </svg>
                                                    {{ __('Encerrar') }}
                                                </span>
                                            @else
                                                <button
                                                    type="button"
                                                    wire:click="closeShift({{ $shift->id }})"
                                                    wire:confirm="{{ __('Encerrar o turno #:id agora? Esta ação não pode ser desfeita.', ['id' => $shift->id]) }}"
                                                    wire:loading.attr="disabled"
                                                    wire:target="closeShift({{ $shift->id }})"
                                                    class="inline-flex items-center gap-1.5 rounded-lg border border-red-200 bg-red-50 px-2.5 py-1 text-xs font-medium text-red-600 transition hover:border-red-300 hover:bg-red-100 disabled:opacity-50 dark:border-red-800 dark:bg-red-900/20 dark:text-red-400 dark:hover:bg-red-900/40">
                                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                        <path stroke-linecap="round" stroke-linejoin="round" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 10a1 1 0 011-1h4a1 1 0 011 1v4a1 1 0 01-1 1h-4a1 1 0 01-1-1v-4z"/>
                                                    </svg>
                                                    <span wire:loading.remove wire:target="closeShift({{ $shift->id }})">{{ __('Encerrar') }}</span>
                                                    <span wire:loading wire:target="closeShift({{ $shift->id }})">{{ __('Encerrando…') }}</span>
                                                </button>
                                            @endif
                                        @endif
                                    @endcan
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </flux:card>
</div>
