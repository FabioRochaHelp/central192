@props([
    'status',
    'size' => 'sm',
])

@php
    /** @var \App\Domain\Operations\Enums\IncidentStatus $status */
    $color = match ($status) {
        \App\Domain\Operations\Enums\IncidentStatus::Open => 'blue',
        \App\Domain\Operations\Enums\IncidentStatus::Dispatched, \App\Domain\Operations\Enums\IncidentStatus::InProgress => 'cyan',
        \App\Domain\Operations\Enums\IncidentStatus::PendingNurseReport => 'amber',
        \App\Domain\Operations\Enums\IncidentStatus::PendingFinalReport => 'orange',
        \App\Domain\Operations\Enums\IncidentStatus::Closed => 'zinc',
        \App\Domain\Operations\Enums\IncidentStatus::Qta => 'orange',
        \App\Domain\Operations\Enums\IncidentStatus::Cancelled => 'red',
        \App\Domain\Operations\Enums\IncidentStatus::Logged    => 'zinc',
    };
@endphp

<flux:badge :color="$color" :size="$size" :inset="true">{{ $status->label() }}</flux:badge>
