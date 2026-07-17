<div class="cco-page-gap" @if ($hasPending) wire:poll.6s @endif>
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div class="flex items-center gap-3">
            <span class="flex size-11 shrink-0 items-center justify-center rounded-xl bg-orange-50 text-orange-600 ring-1 ring-orange-100 dark:bg-orange-900/25 dark:text-orange-400 dark:ring-orange-900/40">
                <flux:icon.fire class="size-6" />
            </span>
            <div>
                <flux:heading size="xl" class="tracking-tight text-slate-800 dark:text-slate-100">{{ __('Cicatrizes de incêndio') }}</flux:heading>
                <flux:text class="mt-0.5 text-slate-600 dark:text-slate-400">{{ __('Selecione ocorrências de um período para estimar a área queimada e a severidade (burn scar).') }}</flux:text>
            </div>
        </div>
        <flux:button variant="ghost" icon="map" :href="route('dashboard.fire-map')" wire:navigate>{{ __('Mapa de focos') }}</flux:button>
    </div>

    @if ($message)
        <flux:callout variant="success" dismissible>{{ $message }}</flux:callout>
    @endif

    {{-- Filtro + seleção de ocorrências --}}
    <flux:card class="space-y-4">
        <x-card-heading tone="orange" :subtitle="__('Marque as ocorrências e solicite a análise — os dados são puxados de cada ocorrência.')">
            <x-slot:icon><flux:icon.map-pin class="size-4" /></x-slot:icon>
            {{ __('Ocorrências do período') }}
        </x-card-heading>

        <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-4">
            <flux:input wire:model.live="from" type="date" :label="__('De')" />
            <flux:input wire:model.live="to" type="date" :label="__('Até')" />
            <div class="flex items-end">
                <flux:checkbox wire:model.live="onlyForest" :label="__('Somente incêndio florestal')" />
            </div>
        </div>

        {{-- Campos digitáveis opcionais — padrão é puxar da ocorrência --}}
        <div class="grid gap-4 rounded-lg border border-dashed border-zinc-300 p-3 dark:border-zinc-700 md:grid-cols-3">
            <flux:input wire:model="overrideBioma" :label="__('Bioma (opcional)')" placeholder="{{ __('Padrão: vazio') }}" />
            <flux:input wire:model="overrideSensor" :label="__('Sensor (opcional)')" placeholder="Sentinel-2" />
            <flux:input wire:model="overridePreFireDate" type="date" :label="__('Data pré-fogo (opcional)')" />
        </div>

        @error('selected')
            <flux:callout variant="danger">{{ $message }}</flux:callout>
        @enderror

        <div class="flex flex-wrap items-center gap-2">
            <flux:button size="sm" variant="ghost" wire:click="selectAll">{{ __('Selecionar todas') }}</flux:button>
            <flux:button size="sm" variant="ghost" wire:click="clearSelection">{{ __('Limpar') }}</flux:button>
            <flux:spacer />
            <flux:button variant="primary" color="orange" icon="fire" wire:click="requestSelected" wire:loading.attr="disabled" wire:target="requestSelected">
                {{ __('Solicitar cicatriz das selecionadas') }} ({{ count($selected) }})
            </flux:button>
        </div>

        <div class="overflow-x-auto rounded-xl border border-zinc-200 dark:border-zinc-700">
            <table class="min-w-full divide-y divide-zinc-200 text-start text-sm dark:divide-zinc-700">
                <thead class="bg-blue-50/70 text-slate-600 dark:bg-blue-950/40 dark:text-slate-300">
                    <tr>
                        <th class="px-3 py-3 w-10"></th>
                        <th class="px-3 py-3 text-start font-medium">{{ __('Talão') }}</th>
                        <th class="px-3 py-3 text-start font-medium">{{ __('Data') }}</th>
                        <th class="px-3 py-3 text-start font-medium">{{ __('Natureza') }}</th>
                        <th class="px-3 py-3 text-start font-medium">{{ __('Município') }}</th>
                        <th class="px-3 py-3 text-start font-medium">{{ __('Coordenadas') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-100 bg-white dark:divide-zinc-800 dark:bg-zinc-900">
                    @forelse ($incidents as $incident)
                        <tr wire:key="inc-{{ $incident->id }}" class="hover:bg-zinc-50 dark:hover:bg-zinc-800/50">
                            <td class="px-3 py-2">
                                <flux:checkbox wire:model.live="selected" value="{{ $incident->id }}" />
                            </td>
                            <td class="px-3 py-2 font-medium">{{ $incident->talao ?? __('s/ talão') }}/{{ $incident->dispatch_year }}</td>
                            <td class="px-3 py-2 text-xs">{{ $incident->occurred_at?->format('d/m/Y H:i') ?? '—' }}</td>
                            <td class="px-3 py-2">{{ $incident->nature?->name ?? '—' }}</td>
                            <td class="px-3 py-2">{{ $incident->city ?? $incident->municipio?->city ?? '—' }}</td>
                            <td class="px-3 py-2 text-xs text-zinc-500">{{ number_format((float) $incident->latitude, 4) }}, {{ number_format((float) $incident->longitude, 4) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="px-4 py-6 text-zinc-500">{{ __('Nenhuma ocorrência com coordenadas no período.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </flux:card>

    {{-- Resultados das análises --}}
    <flux:card class="space-y-4">
        <x-card-heading tone="blue" :subtitle="__('Resultados das cicatrizes solicitadas.')">
            <x-slot:icon><flux:icon.clipboard-document-list class="size-4" /></x-slot:icon>
            {{ __('Análises') }}
        </x-card-heading>

        <div class="overflow-x-auto rounded-xl border border-zinc-200 dark:border-zinc-700">
            <table class="min-w-full divide-y divide-zinc-200 text-start text-sm dark:divide-zinc-700">
                <thead class="bg-blue-50/70 text-slate-600 dark:bg-blue-950/40 dark:text-slate-300">
                    <tr>
                        <th class="px-4 py-3 text-start font-medium">{{ __('Local') }}</th>
                        <th class="px-4 py-3 text-start font-medium">{{ __('Período') }}</th>
                        <th class="px-4 py-3 text-start font-medium">{{ __('Severidade') }}</th>
                        <th class="px-4 py-3 text-end font-medium">{{ __('Área (ha)') }}</th>
                        <th class="px-4 py-3 text-start font-medium">{{ __('Ocorrência') }}</th>
                        <th class="px-4 py-3 text-start font-medium">{{ __('Status') }}</th>
                        <th class="px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-100 bg-white dark:divide-zinc-800 dark:bg-zinc-900">
                    @forelse ($analyses as $analysis)
                        @php($severity = $analysis->severity())
                        <tr wire:key="scar-{{ $analysis->id }}">
                            <td class="px-4 py-3">
                                <div class="font-medium">{{ $analysis->municipio ?? '—' }}{{ $analysis->estado ? '/'.$analysis->estado : '' }}</div>
                                <div class="text-xs text-zinc-500">{{ number_format((float) $analysis->latitude, 4) }}, {{ number_format((float) $analysis->longitude, 4) }}</div>
                            </td>
                            <td class="px-4 py-3 text-xs">
                                {{ $analysis->pre_fire_date?->format('d/m/Y') ?? '—' }}
                                → {{ $analysis->post_fire_date?->format('d/m/Y') ?? '—' }}
                            </td>
                            <td class="px-4 py-3">
                                @if ($severity)
                                    <span class="inline-flex items-center gap-1.5">
                                        <span class="inline-block size-3 rounded-full" style="background: {{ $severity->color() }}"></span>
                                        {{ $severity->label() }}
                                    </span>
                                @else
                                    <span class="text-zinc-400">—</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-end">{{ $analysis->area_ha !== null ? number_format((float) $analysis->area_ha, 2, ',', '.') : '—' }}</td>
                            <td class="px-4 py-3 text-xs">
                                @if ($analysis->incident)
                                    {{ $analysis->incident->talao ?? __('s/ talão') }}/{{ $analysis->incident->dispatch_year }}
                                @else
                                    <span class="text-zinc-400">{{ __('Avulsa') }}</span>
                                @endif
                            </td>
                            <td class="px-4 py-3">
                                <flux:badge size="sm" :color="$analysis->status->color()">{{ $analysis->status->label() }}</flux:badge>
                                @if ($analysis->status->value === 'ERRO' && $analysis->error_message)
                                    <div class="mt-1 max-w-[16rem] truncate text-xs text-red-500" title="{{ $analysis->error_message }}">{{ $analysis->error_message }}</div>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-end">
                                <div class="flex items-center justify-end gap-1">
                                    <flux:button size="xs" variant="ghost" icon="eye" :href="route('operations.reports.fire-scars.show', $analysis)" wire:navigate />
                                    @if ($analysis->status->value === 'ERRO')
                                        <flux:button size="xs" variant="ghost" icon="arrow-path" wire:click="retry({{ $analysis->id }})" />
                                    @endif
                                    <flux:button size="xs" variant="ghost" icon="trash" wire:click="delete({{ $analysis->id }})" wire:confirm="{{ __('Remover esta análise?') }}" />
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="px-4 py-6 text-zinc-500">{{ __('Nenhuma análise registrada.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </flux:card>

    {{-- Análise avulsa por coordenada (opcional) --}}
    <flux:card x-data="{ open: {{ $latitude !== '' || $longitude !== '' ? 'true' : 'false' }} }" class="space-y-4">
        <button type="button" class="flex w-full items-center justify-between text-start" @click="open = !open">
            <x-card-heading tone="blue">
                <x-slot:icon><flux:icon.map class="size-4" /></x-slot:icon>
                {{ __('Análise avulsa por coordenada') }}
            </x-card-heading>
            <flux:icon.chevron-down class="size-4 text-zinc-400 transition-transform" x-bind:class="open && 'rotate-180'" />
        </button>

        <form x-show="open" x-cloak wire:submit="createStandalone" class="grid gap-4 md:grid-cols-2 lg:grid-cols-4">
            <flux:input wire:model="latitude" :label="__('Latitude')" placeholder="-22.1256" />
            <flux:input wire:model="longitude" :label="__('Longitude')" placeholder="-51.3889" />
            <flux:input wire:model="municipio" :label="__('Município')" />
            <flux:input wire:model="estado" :label="__('UF')" maxlength="2" placeholder="SP" />
            <flux:input wire:model="bioma" :label="__('Bioma')" placeholder="Cerrado" />
            <div class="flex items-end md:col-span-2 lg:col-span-4">
                <flux:button type="submit" variant="primary" color="orange" icon="fire" wire:loading.attr="disabled" wire:target="createStandalone">
                    {{ __('Solicitar análise avulsa') }}
                </flux:button>
            </div>
        </form>
    </flux:card>
</div>
