@php
    $callIntakeExpiresLabel = null;
    $rawExpiry = $callIntakePrefill['expires_at'] ?? '';
    if (is_string($rawExpiry) && $rawExpiry !== '') {
        try {
            $callIntakeExpiresLabel = \Carbon\CarbonImmutable::parse($rawExpiry)
                ->timezone(config('app.timezone'))
                ->format('d/m/Y H:i');
        } catch (\Throwable) {
            $callIntakeExpiresLabel = null;
        }
    }
@endphp

<flux:modal wire:model.self="showCallIntakeModal" wire:close="closeCallIntakeModal" variant="floating"
    :closable="false" :dismissible="false"
    class="max-h-[92vh] w-full max-w-[min(96rem,calc(100vw-1.5rem))] overflow-y-auto">
    @if ($showCallIntakeModal)
        <div class="space-y-4">
            <div
                class="flex flex-wrap items-start justify-between gap-3 border-b border-slate-200/90 pb-4 dark:border-slate-700/60">
                <div class="min-w-0 flex-1">
                    <flux:heading size="lg">{{ __('Nova chamada') }}</flux:heading>
                </div>
            </div>

            <livewire:operations.incident-create wire:key="call-intake-{{ $callIntakeRenderKey }}"
                embedded-in-modal="true" caller-phone="{{ e($callIntakePrefill['phone'] ?? '') }}" :caller-name="$callIntakePrefill['caller_name'] ?? ''"
                :latitude="$callIntakePrefill['latitude'] ?? ''" :longitude="$callIntakePrefill['longitude'] ?? ''" :call-received-at="$callIntakePrefill['call_received_at'] ?? ''" :reference-notes="$callIntakePrefill['external_reference'] ?? ''"
                :intake-alert-id="$callIntakePrefill['alert_id'] ?? null" />
        </div>
    @endif
</flux:modal>
