<?php

declare(strict_types=1);

namespace App\Domain\Operations\Actions;

use App\Domain\Operations\Enums\IncidentReportModality;
use App\Domain\Operations\Enums\IncidentStatus;
use App\Domain\Operations\Events\FinalReportFilled;
use App\Domain\Operations\Events\IncidentFinalized;
use App\Domain\Operations\Services\IncidentTimelineRecorder;
use App\Models\FireBuildingReport;
use App\Models\FireForestReport;
use App\Models\Incident;
use App\Models\IncidentFinalReport;
use App\Models\RescueAnimalReport;
use App\Models\RescueInsectReport;
use App\Models\RescueOtherReport;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Persiste o relatório final CB (Incêndio / Salvamento) e fecha a ocorrência.
 *
 * @see docs/migracao/modalidades-relatorios.md — SaveFinalReportAction
 */
final class SaveFinalReportAction
{
    public function __construct(
        private IncidentTimelineRecorder $timeline,
    ) {}

    /**
     * @param  array<string, mixed>  $baseData  Campos de `incident_final_reports`
     * @param  array<string, mixed>  $specificData  Campos da sub-tabela por modalidade
     */
    public function execute(
        Incident $incident,
        User $author,
        IncidentReportModality $modality,
        array $baseData,
        array $specificData,
    ): IncidentFinalReport {
        return DB::transaction(function () use ($incident, $author, $modality, $baseData, $specificData): IncidentFinalReport {
            if (! $modality->usesFinalReport()) {
                throw new RuntimeException('Esta modalidade não usa relatório final CB.');
            }

            /** @var IncidentFinalReport $report */
            $report = IncidentFinalReport::query()->updateOrCreate(
                ['incident_id' => $incident->id],
                array_merge($baseData, [
                    'user_id' => $author->id,
                    'modality' => $modality,
                    'submitted_at' => now(),
                ]),
            );

            $this->saveSpecificReport($report, $modality, $specificData);

            $this->timeline->record($incident, 'final_report_saved', [
                'report_id' => $report->id,
                'modality' => $modality->value,
                'user_id' => $author->id,
            ], $author);

            $fresh = $incident->fresh();
            if ($fresh !== null && $fresh->status === IncidentStatus::PendingFinalReport) {
                $fresh->update(['status' => IncidentStatus::Closed]);
                $this->timeline->record($fresh, 'incident_closed', [
                    'via_final_report' => true,
                ], $author);
                IncidentFinalized::dispatch($fresh->fresh());
            }

            FinalReportFilled::dispatch($report->fresh());

            return $report;
        });
    }

    /** @param  array<string, mixed>  $data */
    private function saveSpecificReport(IncidentFinalReport $report, IncidentReportModality $modality, array $data): void
    {
        match ($modality) {
            IncidentReportModality::FireForest => FireForestReport::query()->updateOrCreate(
                ['incident_final_report_id' => $report->id],
                $data,
            ),
            IncidentReportModality::FireBuilding => FireBuildingReport::query()->updateOrCreate(
                ['incident_final_report_id' => $report->id],
                $data,
            ),
            IncidentReportModality::RescueAnimal => RescueAnimalReport::query()->updateOrCreate(
                ['incident_final_report_id' => $report->id],
                $data,
            ),
            IncidentReportModality::RescueInsects => RescueInsectReport::query()->updateOrCreate(
                ['incident_final_report_id' => $report->id],
                $data,
            ),
            IncidentReportModality::RescueOther => RescueOtherReport::query()->updateOrCreate(
                ['incident_final_report_id' => $report->id],
                $data,
            ),
            default => null,
        };
    }
}
