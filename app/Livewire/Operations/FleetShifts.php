<?php

declare(strict_types=1);

namespace App\Livewire\Operations;

use App\Domain\Operations\Enums\ShiftStatus;
use App\Domain\Operations\Events\ShiftUpdated;
use App\Models\Shift;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Attributes\Title;
use Livewire\Component;

/** Turnos ativos e estado das viaturas (equivalente a `/api/viaturas` / gestão de turno). */
#[Layout('layouts.app')]
#[Title('Turnos e viaturas')]
final class FleetShifts extends Component
{
    public string $message = '';

    /** Reverb: turno criado ou encerrado — atualiza a lista de viaturas/turnos. */
    #[On('fleet-shifts-refresh')]
    public function refreshFromBroadcast(): void {}

    public function mount(): void
    {
        abort_unless(Auth::user()?->hasOperationalAbility('dispatch.view'), 403);
    }

    public function closeShift(int $id): void
    {
        $this->message = '';
        $this->resetErrorBag();

        $shift = Shift::query()->findOrFail($id);
        $this->authorize('update', $shift);

        if (! $shift->starts_at->isPast() || ! $shift->ends_at->isFuture()) {
            $this->addError('close', __('Este turno não está ativo no momento.'));

            return;
        }

        if ($shift->incidentDispatches()->exists()) {
            $this->addError('close', __('Não é possível encerrar: viatura está em despacho ativo.'));

            return;
        }

        $shift->update([
            'ends_at' => now(),
            'status' => ShiftStatus::Baixado,
        ]);

        ShiftUpdated::dispatch($shift->fresh());

        $this->message = __('Turno #:id encerrado antecipadamente.', ['id' => $id]);
    }

    public function render(): View
    {
        /** @var User $user */
        $user = Auth::user();

        $query = Shift::query()
            ->with(['vehicle', 'municipio'])
            ->where('ends_at', '>=', now()->subDay());

        if ($user->hasMultiMunicipioAccess()) {
            $sid = session('operational_municipio_id');
            if ($sid !== null) {
                $query->where('municipio_id', (int) $sid);
            }
        } elseif ($user->municipio_id !== null) {
            $query->where('municipio_id', (int) $user->municipio_id);
        }

        $shifts = $query->orderByDesc('starts_at')->limit(80)->get();

        return view('livewire.operations.fleet-shifts', [
            'shifts' => $shifts,
        ]);
    }
}
