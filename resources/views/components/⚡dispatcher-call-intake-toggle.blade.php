<?php

use App\Domain\Operations\Enums\UserLegacyProfile;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

new class extends Component {
    public bool $enabled = false;

    public function mount(): void
    {
        $user = Auth::user();
        abort_unless($user instanceof User && $user->legacyProfile() === UserLegacyProfile::Dispatcher, 403);

        $this->enabled = (bool) $user->call_intake_websocket_enabled;
    }

    public function updatedEnabled(bool $value): void
    {
        $user = Auth::user();
        abort_unless($user instanceof User && $user->legacyProfile() === UserLegacyProfile::Dispatcher, 403);

        $user->update(['call_intake_websocket_enabled' => $value]);

        $this->dispatch('call-intake-preference-changed', enabled: $value);
    }
};
?>

<div class="px-3 py-2">
    <flux:switch wire:model.live="enabled" :label="__('Atender chamadas')" align="left"
        data-test="dispatcher-call-intake-toggle" />
</div>
