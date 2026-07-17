@props([
    'itemId',
    'method' => 'edit',
])

<flux:button
    type="button"
    size="sm"
    variant="ghost"
    icon="pencil-square"
    class="!text-sky-600 hover:!bg-sky-500/15 dark:!text-sky-400 dark:hover:!bg-sky-400/15"
    wire:click="{{ $method }}({{ $itemId }})"
    :title="__('Editar')"
/>
