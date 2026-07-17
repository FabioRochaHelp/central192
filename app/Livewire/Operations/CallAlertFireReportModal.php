<?php

declare(strict_types=1);

namespace App\Livewire\Operations;

use App\Support\Operations\OperationalCallAlertFireReport;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\On;
use Livewire\Component;

final class CallAlertFireReportModal extends Component
{
    public bool $showModal = false;

    /** @var array<string, mixed> */
    public array $report = [];

    #[On('call-alert-fire-report')]
    public function open(string $locationKey): void
    {
        $user = Auth::user();
        if ($user === null || ! $user->hasOperationalAbility('dispatch.view')) {
            return;
        }

        $report = OperationalCallAlertFireReport::fromLocationKey($locationKey);
        if ($report === null) {
            return;
        }

        $this->report = $report;
        $this->showModal = true;
    }

    public function close(): void
    {
        $this->showModal = false;
        $this->report = [];
    }

    public function render(): View
    {
        return view('livewire.operations.call-alert-fire-report-modal');
    }
}
