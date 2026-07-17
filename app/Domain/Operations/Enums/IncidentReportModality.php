<?php

declare(strict_types=1);

namespace App\Domain\Operations\Enums;

/**
 * Modalidade do relatório final da ocorrência — derivada de `Nature.report_modality`.
 *
 * @see docs/migracao/modalidades-relatorios.md
 */
enum IncidentReportModality: string
{
    case Samu = 'samu';
    case FireForest = 'fire_forest';
    case FireBuilding = 'fire_building';
    case RescueAnimal = 'rescue_animal';
    case RescueInsects = 'rescue_insects';
    case RescueOther = 'rescue_other';

    public function label(): string
    {
        return match ($this) {
            self::Samu => 'SAMU',
            self::FireForest => 'Incêndio florestal',
            self::FireBuilding => 'Incêndio em edificação',
            self::RescueAnimal => 'Salvamento animal',
            self::RescueInsects => 'Insetos agressivos',
            self::RescueOther => 'Outro salvamento',
        };
    }

    /** Modalidades CB que encerram em `left_scene` (sem estágios hospitalares obrigatórios). */
    public function closesAtLeftScene(): bool
    {
        return match ($this) {
            self::Samu => false,
            default => true,
        };
    }

    /** Modalidades CB (Corpo de Bombeiros) — relatório final via `SaveFinalReportAction`. */
    public function usesFinalReport(): bool
    {
        return $this !== self::Samu;
    }
}
