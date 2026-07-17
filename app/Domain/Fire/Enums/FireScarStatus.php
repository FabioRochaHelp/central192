<?php

declare(strict_types=1);

namespace App\Domain\Fire\Enums;

/**
 * Ciclo de vida da análise de cicatriz de incêndio (burn scar).
 *
 * @see .agents/skills/wildfire-scar-analysis/SKILL.md
 */
enum FireScarStatus: string
{
    case Pendente = 'PENDENTE';
    case Processando = 'PROCESSANDO';
    case Concluido = 'CONCLUIDO';
    case Erro = 'ERRO';

    public function label(): string
    {
        return match ($this) {
            self::Pendente => 'Pendente',
            self::Processando => 'Processando',
            self::Concluido => 'Concluído',
            self::Erro => 'Erro',
        };
    }

    /** Cor Tailwind (badge) por status. */
    public function color(): string
    {
        return match ($this) {
            self::Pendente => 'zinc',
            self::Processando => 'amber',
            self::Concluido => 'emerald',
            self::Erro => 'red',
        };
    }

    public function isTerminal(): bool
    {
        return $this === self::Concluido || $this === self::Erro;
    }
}
