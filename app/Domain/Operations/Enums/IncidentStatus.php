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
    /** Aguardando regulação médica — natureza exige regulação; ainda não empenhável. */
    case PendingRegulation = 'pending_regulation';
    /** Médico regulador assumiu a ocorrência; regulação em andamento. */
    case InRegulation = 'in_regulation';
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
    /** Encerrada na regulação — orientação médica, transferência ou recusa (sem viatura). */
    case RegulationDenied = 'regulation_denied';
    /** Chamada simples (C/T/A) — sem despacho, sem relatório, só contabiliza. */
    case Logged = 'logged';

    public function label(): string
    {
        return match ($this) {
            self::PendingRegulation => 'Aguardando regulação',
            self::InRegulation => 'Em regulação',
            self::RegulationDenied => 'Encerrada na regulação',
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

    /** Está na regulação médica (aguardando ou em andamento). */
    public function isUnderRegulation(): bool
    {
        return in_array($this, [self::PendingRegulation, self::InRegulation], true);
    }

    /** Status que compõem a fila da tela de regulação médica. */
    public static function regulationQueue(): array
    {
        return [self::PendingRegulation, self::InRegulation];
    }
}
