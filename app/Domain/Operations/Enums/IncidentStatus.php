<?php

declare(strict_types=1);

namespace App\Domain\Operations\Enums;

/**
 * Ciclo principal da ocorrência (normalização do status numérico legado).
 *
 * @see docs/migracao/regras-negocio.md
 */
enum IncidentStatus: string
{
    case Open = 'open';
    case Dispatched = 'dispatched';
    case InProgress = 'in_progress';
    /** Viatura liberada; encerramento SAMU só após relatório de enfermagem. */
    case PendingNurseReport = 'pending_nurse_report';
    /** Viatura liberada; encerramento CB (Incêndio/Salvamento) só após relatório final. */
    case PendingFinalReport = 'pending_final_report';
    case Closed = 'closed';
    case Qta = 'qta';
    case Cancelled = 'cancelled';
    /** Chamada simples (C/T/A) — sem despacho, sem relatório, só contabiliza. */
    case Logged = 'logged';

    public function label(): string
    {
        return match ($this) {
            self::Open => 'Aberta',
            self::Dispatched => 'Despachada',
            self::InProgress => 'Em atendimento',
            self::PendingNurseReport => 'Pendente — relatório de enfermagem',
            self::PendingFinalReport => 'Pendente — relatório final',
            self::Closed => 'Encerrada',
            self::Qta => 'QTA',
            self::Cancelled => 'Cancelada',
            self::Logged => 'Registrada',
        };
    }

    public function isLogged(): bool
    {
        return $this === self::Logged;
    }
}
