@error('tenant')
    <flux:callout variant="danger">{{ $message }}</flux:callout>
@enderror

@error('vehicle')
    <flux:callout variant="danger">{{ $message }}</flux:callout>
@enderror

@error('board')
    <flux:callout variant="danger">{{ $message }}</flux:callout>
@enderror

@if ($boardMessage !== '')
    <flux:callout variant="success">{{ $boardMessage }}</flux:callout>
@endif
