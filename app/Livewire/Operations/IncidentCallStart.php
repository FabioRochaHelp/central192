<?php

declare(strict_types=1);

namespace App\Livewire\Operations;

use App\Models\Incident;
use App\Support\Operations\IncidentPhoneNormalizer;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/** Primeira etapa do cadastro manual: identifica a chamada pelo telefone (fluxo legado). */
#[Layout('layouts.app')]
#[Title('Nova ocorrência — chamada')]
final class IncidentCallStart extends Component
{
    public string $caller_phone = '';

    public function mount(): void
    {
        Gate::authorize('viewAny', Incident::class);
        abort_unless(Auth::user()?->hasOperationalAbility('incident.create'), 403);
    }

    public function continueToForm(): void
    {
        $this->resetErrorBag();

        $this->validate([
            'caller_phone' => ['required', 'string', 'max:64'],
        ]);

        $normalized = IncidentPhoneNormalizer::normalize($this->caller_phone);
        if (! IncidentPhoneNormalizer::passesMinimumLength($normalized)) {
            $this->addError('caller_phone', __('Informe ao menos 8 dígitos do número.'));

            return;
        }

        session()->put('operations.incident_intake', [
            'caller_phone' => $normalized,
        ]);

        $this->redirect(route('operations.incidents.create'), navigate: true);
    }

    public function render(): View
    {
        return view('livewire.operations.incident-call-start');
    }
}
