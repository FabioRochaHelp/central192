@props([
    'itemId',
    'method' => 'delete',
    'confirmMessage',
])

<flux:button
    type="button"
    size="sm"
    variant="ghost"
    icon="trash"
    class="!text-rose-600 hover:!bg-rose-500/15 dark:!text-rose-400 dark:hover:!bg-rose-400/15"
    wire:click="{{ $method }}({{ $itemId }})"
    wire:confirm="{{ $confirmMessage }}"
    :title="__('Excluir')"
/>
