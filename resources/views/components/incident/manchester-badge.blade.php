@props([
    'risk' => null,
    'size' => 'sm',
    'showPrefix' => true,
])

@php
    /** @var \App\Domain\Operations\Enums\ManchesterRisk|null $risk */
@endphp

@if ($risk)
    <flux:badge
        :color="$risk->fluxColor()"
        :size="$size"
        icon="exclamation-triangle"
        icon:variant="mini"
    >
        {{ $showPrefix ? __('Manchester: :label', ['label' => $risk->label()]) : $risk->label() }}
    </flux:badge>
@endif
