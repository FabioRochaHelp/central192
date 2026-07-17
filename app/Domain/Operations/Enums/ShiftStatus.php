<?php

declare(strict_types=1);

namespace App\Domain\Operations\Enums;

enum ShiftStatus: string
{
    case Disponivel = 'DISPONIVEL';
    case Empenhado = 'EMPENHADO';
    case Baixado = 'BAIXADO';
    case Oficina = 'OFICINA';
    case Acidente = 'ACIDENTE';

    public function label(): string
    {
        return match ($this) {
            self::Disponivel => 'Disponível',
            self::Empenhado => 'Empenhado',
            self::Baixado => 'Baixado',
            self::Oficina => 'Oficina',
            self::Acidente => 'Acidente',
        };
    }

    /** Status permitidos na abertura manual de turno. */
    public static function openableCases(): array
    {
        return [
            self::Disponivel,
            self::Baixado,
            self::Oficina,
            self::Acidente,
        ];
    }

    public function requiresStaffOnOpen(): bool
    {
        return match ($this) {
            self::Disponivel, self::Empenhado => true,
            self::Baixado, self::Oficina, self::Acidente => false,
        };
    }

    public function requiresChecklistOnOpen(): bool
    {
        return $this === self::Disponivel;
    }

    public function isOperationalIdleState(): bool
    {
        return match ($this) {
            self::Baixado, self::Oficina, self::Acidente => true,
            default => false,
        };
    }

    /**
     * Destaque na coluna lateral do CCO (viaturas indisponíveis para empenho).
     *
     * @return array{row: string, badge: string, icon: string}
     */
    public function dispatchIdleAccentClasses(): array
    {
        return match ($this) {
            self::Baixado => [
                'row' => 'border-zinc-300/90 bg-zinc-50/95 dark:border-zinc-600/70 dark:bg-zinc-900/50',
                'badge' => 'bg-zinc-200 text-zinc-800 dark:bg-zinc-700 dark:text-zinc-100',
                'icon' => 'text-zinc-500 dark:text-zinc-400',
            ],
            self::Oficina => [
                'row' => 'border-blue-300/90 bg-blue-50/95 dark:border-blue-800/60 dark:bg-blue-950/35',
                'badge' => 'bg-blue-200 text-blue-900 dark:bg-blue-900/60 dark:text-blue-100',
                'icon' => 'text-blue-600 dark:text-blue-400',
            ],
            self::Acidente => [
                'row' => 'border-red-300/90 bg-red-50/95 dark:border-red-900/60 dark:bg-red-950/35',
                'badge' => 'bg-red-200 text-red-900 dark:bg-red-900/60 dark:text-red-100',
                'icon' => 'text-red-600 dark:text-red-400',
            ],
            default => [
                'row' => 'border-slate-200/90 bg-white/90 dark:border-slate-700/60 dark:bg-slate-900/35',
                'badge' => 'bg-slate-200 text-slate-800 dark:bg-slate-700 dark:text-slate-100',
                'icon' => 'text-slate-500 dark:text-slate-400',
            ],
        };
    }
}
