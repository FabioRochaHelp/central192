<?php

declare(strict_types=1);

namespace App\Livewire\Operations;

use App\Domain\Operations\Actions\SaveIncidentNurseReportAction;
use App\Models\Incident;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Throwable;

/**
 * Relatório de enfermagem — obrigatório para concluir o encerramento após retorno à base.
 *
 * @see docs/migracao/controllers-models.md — Relatorio
 * @see docs/migracao/fluxo-ocorrencias.md
 */
#[Layout('layouts.app')]
#[Title('Relatório de enfermagem')]
final class IncidentNurseReport extends Component
{
    public Incident $incident;

    public string $clinical_evolution = '';

    public string $conduct_summary = '';

    public string $destination_notes = '';

    public function mount(Incident $incident): void
    {
        Gate::authorize('fillNurseReport', $incident);

        $this->incident = $incident->load(['nurseReport', 'nature']);

        if ($report = $this->incident->nurseReport) {
            $this->clinical_evolution = $report->clinical_evolution;
            $this->conduct_summary = (string) ($report->conduct_summary ?? '');
            $this->destination_notes = (string) ($report->destination_notes ?? '');
        }
    }

    public function save(SaveIncidentNurseReportAction $action): void
    {
        Gate::authorize('fillNurseReport', $this->incident);

        $validated = $this->validate([
            'clinical_evolution' => ['required', 'string', 'max:10000'],
            'conduct_summary' => ['nullable', 'string', 'max:5000'],
            'destination_notes' => ['nullable', 'string', 'max:5000'],
        ], [], [
            'clinical_evolution' => __('Evolução / relatório assistencial'),
        ]);

        $user = Auth::user();
        abort_unless($user instanceof User, 403);

        try {
            $action->execute(
                $this->incident->fresh(),
                $user,
                $validated['clinical_evolution'],
                $validated['conduct_summary'] !== '' ? $validated['conduct_summary'] : null,
                $validated['destination_notes'] !== '' ? $validated['destination_notes'] : null,
            );
        } catch (AuthorizationException $e) {
            $this->addError('save', $e->getMessage());

            return;
        } catch (Throwable $e) {
            report($e);
            $this->addError('save', __('Não foi possível salvar o relatório.'));

            return;
        }

        $this->redirect(route('operations.incidents.show', $this->incident), navigate: true);
    }

    public function render(): View
    {
        return view('livewire.operations.incident-nurse-report');
    }
}
