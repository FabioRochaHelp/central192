<div class="cco-page-gap">
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div class="flex items-center gap-3">
            <span class="flex size-11 shrink-0 items-center justify-center rounded-xl bg-indigo-50 text-indigo-700 ring-1 ring-indigo-100 dark:bg-indigo-900/30 dark:text-indigo-300 dark:ring-indigo-900/40">
                <flux:icon.clipboard-document-check class="size-6" />
            </span>
            <div>
                <flux:heading size="xl" class="tracking-tight text-slate-800 dark:text-slate-100">{{ __('Relatório de regulação médica') }}</flux:heading>
                <flux:text class="mt-0.5 text-slate-600 dark:text-slate-400">{{ __('Indicadores de regulação por período, município, médico, decisão e prioridade.') }}</flux:text>
            </div>
        </div>
        <div class="flex items-center gap-2">
            <flux:button variant="ghost" icon="table-cells" wire:click="exportCsv">{{ __('CSV') }}</flux:button>
            <flux:button variant="primary" color="indigo" icon="document-arrow-down" :href="route('operations.reports.regulation.document', ['from' => $from, 'to' => $to, 'municipio_id' => $municipioId, 'regulator_id' => $regulatorId, 'decision' => $decision, 'priority' => $priority])" target="_blank">{{ __('PDF') }}</flux:button>
        </div>
    </div>

    <flux:card>
        <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-6">
            <flux:input wire:model.live="from" type="date" :label="__('De')" />
            <flux:input wire:model.live="to" type="date" :label="__('Até')" />
            <flux:select wire:model.live="municipioId" :label="__('Município')">
                <flux:select.option value="">{{ __('Todos') }}</flux:select.option>
                @foreach ($municipios as $m)
                    <flux:select.option :value="$m->id">{{ $m->city }}</flux:select.option>
                @endforeach
            </flux:select>
            <flux:select wire:model.live="regulatorId" :label="__('Médico')">
                <flux:select.option value="">{{ __('Todos') }}</flux:select.option>
                @foreach ($regulators as $r)
                    <flux:select.option :value="$r->id">{{ $r->name }}</flux:select.option>
                @endforeach
            </flux:select>
            <flux:select wire:model.live="decision" :label="__('Decisão')">
                <flux:select.option value="">{{ __('Todas') }}</flux:select.option>
                @foreach ($decisions as $d)
                    <flux:select.option :value="$d->value">{{ $d->label() }}</flux:select.option>
                @endforeach
            </flux:select>
            <flux:select wire:model.live="priority" :label="__('Prioridade')">
                <flux:select.option value="">{{ __('Todas') }}</flux:select.option>
                @foreach ($priorities as $p)
                    <flux:select.option :value="$p->value">{{ $p->label() }}</flux:select.option>
                @endforeach
            </flux:select>
        </div>
    </flux:card>

    {{-- KPIs --}}
    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <flux:card class="border-t-2 border-indigo-500">
            <flux:text size="sm" class="text-zinc-500">{{ __('Total de regulações') }}</flux:text>
            <div class="mt-1 text-3xl font-bold tabular-nums text-indigo-600 dark:text-indigo-400">{{ number_format($data['total'], 0, ',', '.') }}</div>
        </flux:card>
        <flux:card class="border-t-2 border-blue-500/70">
            <flux:text size="sm" class="text-zinc-500">{{ __('Tempo médio de regulação') }}</flux:text>
            <div class="mt-1 text-3xl font-bold tabular-nums text-blue-800 dark:text-blue-300">{{ $data['response_time']['avg_response_min'] !== null ? number_format($data['response_time']['avg_response_min'], 1, ',', '.').' min' : '—' }}</div>
        </flux:card>
        <flux:card class="border-t-2 border-green-500/70">
            <flux:text size="sm" class="text-zinc-500">{{ __('% com envio de recurso') }}</flux:text>
            <div class="mt-1 text-3xl font-bold tabular-nums text-green-700 dark:text-green-400">{{ $data['dispatch_pct'] !== null ? number_format($data['dispatch_pct'], 1, ',', '.').'%' : '—' }}</div>
        </flux:card>
        <flux:card class="border-t-2 border-teal-500/70">
            <flux:text size="sm" class="text-zinc-500">{{ __('% resolvido por orientação') }}</flux:text>
            <div class="mt-1 text-3xl font-bold tabular-nums text-teal-700 dark:text-teal-400">{{ $data['guidance_pct'] !== null ? number_format($data['guidance_pct'], 1, ',', '.').'%' : '—' }}</div>
        </flux:card>
    </div>

    <div class="grid gap-4 lg:grid-cols-2">
        <flux:card class="space-y-3">
            <x-card-heading tone="indigo">
                <x-slot:icon><flux:icon.list-bullet class="size-4" /></x-slot:icon>
                {{ __('Por decisão') }}
            </x-card-heading>
            <table class="min-w-full divide-y divide-zinc-200 text-sm dark:divide-zinc-700">
                <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800">
                    @forelse ($data['by_decision'] as $row)
                        <tr><td class="py-2">{{ $row['label'] }}</td><td class="py-2 text-end font-medium tabular-nums">{{ number_format($row['count'], 0, ',', '.') }}</td></tr>
                    @empty
                        <tr><td class="py-4 text-zinc-500">{{ __('Sem dados.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </flux:card>

        <flux:card class="space-y-3">
            <x-card-heading tone="blue">
                <x-slot:icon><flux:icon.flag class="size-4" /></x-slot:icon>
                {{ __('Por prioridade') }}
            </x-card-heading>
            <table class="min-w-full divide-y divide-zinc-200 text-sm dark:divide-zinc-700">
                <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800">
                    @forelse ($data['by_priority'] as $row)
                        <tr><td class="py-2">{{ $row['label'] }}</td><td class="py-2 text-end font-medium tabular-nums">{{ number_format($row['count'], 0, ',', '.') }}</td></tr>
                    @empty
                        <tr><td class="py-4 text-zinc-500">{{ __('Sem dados.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </flux:card>

        <flux:card class="space-y-3">
            <x-card-heading tone="teal">
                <x-slot:icon><flux:icon.truck class="size-4" /></x-slot:icon>
                {{ __('Por recurso indicado') }}
            </x-card-heading>
            <table class="min-w-full divide-y divide-zinc-200 text-sm dark:divide-zinc-700">
                <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800">
                    @forelse ($data['by_resource'] as $row)
                        <tr><td class="py-2">{{ $row['label'] }}</td><td class="py-2 text-end font-medium tabular-nums">{{ number_format($row['count'], 0, ',', '.') }}</td></tr>
                    @empty
                        <tr><td class="py-4 text-zinc-500">{{ __('Sem dados.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </flux:card>

        <flux:card class="space-y-3">
            <x-card-heading tone="indigo">
                <x-slot:icon><flux:icon.user class="size-4" /></x-slot:icon>
                {{ __('Por médico regulador') }}
            </x-card-heading>
            <table class="min-w-full divide-y divide-zinc-200 text-sm dark:divide-zinc-700">
                <thead>
                    <tr class="text-xs uppercase text-zinc-500">
                        <th class="py-1 text-start font-medium">{{ __('Médico') }}</th>
                        <th class="py-1 text-end font-medium">{{ __('Regulações') }}</th>
                        <th class="py-1 text-end font-medium">{{ __('Tempo médio') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800">
                    @forelse ($data['by_regulator'] as $row)
                        <tr>
                            <td class="py-2">{{ $row['name'] }}</td>
                            <td class="py-2 text-end font-medium tabular-nums">{{ number_format($row['count'], 0, ',', '.') }}</td>
                            <td class="py-2 text-end tabular-nums">{{ $row['avg_response_min'] !== null ? number_format($row['avg_response_min'], 1, ',', '.').' min' : '—' }}</td>
                        </tr>
                    @empty
                        <tr><td class="py-4 text-zinc-500" colspan="3">{{ __('Sem dados.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </flux:card>
    </div>
</div>
