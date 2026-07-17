<?php

declare(strict_types=1);

namespace App\Domain\Operations\Actions;

use App\Domain\Operations\Enums\IncidentStatus;
use App\Domain\Operations\Events\IncidentFinalized;
use App\Domain\Operations\Services\IncidentTimelineRecorder;
use App\Models\Incident;
use App\Models\IncidentNurseReport;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

/** Persistência do relatório de enfermagem; transição pendente → encerrada. */
final class SaveIncidentNurseReportAction
{
    public function __construct(
        private IncidentTimelineRecorder $timeline,
    ) {}

    public function execute(
        Incident $incident,
        User $author,
        string $clinicalEvolution,
        ?string $conductSummary,
        ?string $destinationNotes,
    ): IncidentNurseReport {
        return DB::transaction(function () use ($incident, $author, $clinicalEvolution, $conductSummary, $destinationNotes): IncidentNurseReport {
            $existing = IncidentNurseReport::query()->where('incident_id', $incident->id)->first();

            if ($existing !== null) {
                $canEdit = $existing->user_id === $author->id || $author->isOperationalCentral();
                if (! $canEdit) {
                    throw new AuthorizationException(__('Somente o autor do relatório ou a central pode alterar este relatório.'));
                }
            }

            $report = IncidentNurseReport::query()->updateOrCreate(
                ['incident_id' => $incident->id],
                [
                    'user_id' => $author->id,
                    'clinical_evolution' => $clinicalEvolution,
                    'conduct_summary' => $conductSummary,
                    'destination_notes' => $destinationNotes,
                    'submitted_at' => now(),
                ],
            );

            $this->timeline->record($incident, 'nurse_report_saved', [
                'report_id' => $report->id,
                'user_id' => $author->id,
            ], $author);

            $fresh = $incident->fresh();
            if ($fresh !== null && $fresh->status === IncidentStatus::PendingNurseReport) {
                $fresh->update(['status' => IncidentStatus::Closed]);
                $this->timeline->record($fresh, 'incident_closed', [
                    'via_nurse_report' => true,
                ], $author);
                IncidentFinalized::dispatch($fresh->fresh());
            }

            return $report;
        });
    }
}
