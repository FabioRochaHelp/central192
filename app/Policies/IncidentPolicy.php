<?php

declare(strict_types=1);

namespace App\Policies;

use App\Domain\Operations\Enums\IncidentReportModality;
use App\Domain\Operations\Enums\IncidentStatus;
use App\Models\Incident;
use App\Models\User;

final class IncidentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->canViewOperationalIncidents();
    }

    public function view(User $user, Incident $incident): bool
    {
        return $this->viewOperational($user, $incident);
    }

    public function viewOperational(User $user, Incident $incident): bool
    {
        if (! $user->canViewOperationalIncidents()) {
            return false;
        }

        if ($incident->municipio_id === null) {
            return true;
        }

        return $user->canAccessOperationalMunicipio((int) $incident->municipio_id);
    }

    public function dispatchUnit(User $user, Incident $incident): bool
    {
        if (! $user->hasOperationalAbility('dispatch.assign_unit')) {
            return false;
        }

        if ($incident->municipio_id === null) {
            return true;
        }

        return $user->canAccessOperationalMunicipio((int) $incident->municipio_id);
    }

    public function advanceStage(User $user, Incident $incident): bool
    {
        if (! $user->hasOperationalAbility('incident.advance_stage')) {
            return false;
        }

        if ($incident->municipio_id === null) {
            return false;
        }

        return $user->canAccessOperationalMunicipio((int) $incident->municipio_id);
    }

    public function releaseUnit(User $user, Incident $incident): bool
    {
        if (! $user->hasOperationalAbility('incident.close')) {
            return false;
        }

        if ($incident->municipio_id === null) {
            return false;
        }

        return $user->canAccessOperationalMunicipio((int) $incident->municipio_id);
    }

    public function cancel(User $user, Incident $incident): bool
    {
        if (! $user->hasOperationalAbility('incident.close')) {
            return false;
        }

        if ($incident->status !== IncidentStatus::Open) {
            return false;
        }

        if ($incident->municipio_id === null) {
            return true;
        }

        return $user->canAccessOperationalMunicipio((int) $incident->municipio_id);
    }

    public function addObservation(User $user, Incident $incident): bool
    {
        return $this->viewOperational($user, $incident);
    }

    public function createOperational(User $user, ?int $municipioId = null): bool
    {
        if (! $user->hasOperationalAbility('incident.create')) {
            return false;
        }

        if ($municipioId === null) {
            return true;
        }

        return $user->canAccessOperationalMunicipio($municipioId);
    }

    /** Nova vítima na ocorrência — formulário doc vitima + auxiliares (docs/migracao/banco-dados.md). */
    public function recordVictim(User $user, Incident $incident): bool
    {
        if (! $user->hasOperationalAbility('victim.record')) {
            return false;
        }

        return $this->viewOperational($user, $incident);
    }

    /** Relatório de enfermagem — exclusivo da modalidade SAMU. */
    public function fillNurseReport(User $user, Incident $incident): bool
    {
        if (! $user->hasOperationalAbility('incident.nurse_report')) {
            return false;
        }

        if (! in_array($incident->status, [IncidentStatus::PendingNurseReport, IncidentStatus::Closed], true)) {
            return false;
        }

        $modality = $incident->nature?->report_modality;
        if ($modality instanceof IncidentReportModality && $modality !== IncidentReportModality::Samu) {
            return false;
        }

        return $this->viewOperational($user, $incident);
    }

    /** Relatório final CB — Incêndio/Salvamento: liberado em pendência ou edição após encerrada. */
    public function fillFinalReport(User $user, Incident $incident): bool
    {
        if (! $user->hasOperationalAbility('incident.nurse_report')) {
            return false;
        }

        if (! in_array($incident->status, [IncidentStatus::PendingFinalReport, IncidentStatus::Closed], true)) {
            return false;
        }

        $modality = $incident->nature?->report_modality;
        if (! ($modality instanceof IncidentReportModality) || ! $modality->usesFinalReport()) {
            return false;
        }

        return $this->viewOperational($user, $incident);
    }

    /** Impressão/download do relatório final CB já preenchido. */
    public function viewFinalReportDocument(User $user, Incident $incident): bool
    {
        if (! $this->viewOperational($user, $incident)) {
            return false;
        }

        if ($incident->finalReport === null) {
            return false;
        }

        $modality = $incident->nature?->report_modality;

        return $modality instanceof IncidentReportModality && $modality->usesFinalReport();
    }
}
