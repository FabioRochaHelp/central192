<?php

declare(strict_types=1);

namespace App\Livewire\Operations\Fire;

use App\Models\FireScarAnalysis;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Cicatriz de incêndio')]
final class FireScarAnalysisShow extends Component
{
    public FireScarAnalysis $analysis;

    public function mount(FireScarAnalysis $analysis): void
    {
        abort_unless(Auth::user()?->isOperationalCentral(), 403);

        $this->analysis = $analysis->load('incident:id,talao,dispatch_year');
    }

    public function render(): View
    {
        return view('livewire.operations.fire.fire-scar-analysis-show', [
            'severity' => $this->analysis->severity(),
        ]);
    }
}
