<?php

declare(strict_types=1);

namespace App\Livewire\Operations\Victims;

use App\Domain\Operations\Actions\ApprovePrescriptionAction;
use App\Models\Prescription;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Validação de prescrição')]
final class PrescriptionApproval extends Component
{
    public Prescription $prescription;

    public function mount(Prescription $prescription): void
    {
        $this->prescription = $prescription->load([
            'victim.incident',
            'items',
            'medicalStaff',
            'prescribedBy',
            'approvedBy',
        ]);

        Gate::authorize('view', $this->prescription);
    }

    public function approve(ApprovePrescriptionAction $action): void
    {
        $user = Auth::user();
        abort_unless($user instanceof User, 403);
        Gate::authorize('approve', $this->prescription);

        try {
            $this->prescription = $action->execute($this->prescription, $user);
        } catch (ValidationException $e) {
            throw $e;
        } catch (\Throwable $e) {
            report($e);
            $this->addError('approve', __('Não foi possível aprovar a prescrição.'));
        }
    }

    public function render(): View
    {
        return view('livewire.operations.victims.prescription-approval');
    }
}
