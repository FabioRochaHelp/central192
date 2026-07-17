<?php

declare(strict_types=1);

namespace App\Domain\Operations\Enums;

/**
 * Classificação da chamada no formulário/triagem.
 *
 * @see docs/migracao/regras-negocio.md
 */
enum CallType: string
{
    case NotCompleted = 'C';
    case Administrative = 'A';
    case Hoax = 'T';
    case Alert = 'L';
    case Normal = 'N';
    case Urgent = 'U';

    /** Tipos que geram ocorrência operacional (despacho de viatura). */
    public function createsOperationalIncident(): bool
    {
        return match ($this) {
            self::Normal, self::Urgent, self::Alert => true,
            default => false,
        };
    }

    /** Tipos "simples": apenas o telefone é obrigatório no formulário. */
    public function isSimple(): bool
    {
        return match ($this) {
            self::NotCompleted, self::Hoax, self::Administrative => true,
            default => false,
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::NotCompleted => __('Não completada'),
            self::Administrative => __('Administrativo'),
            self::Hoax => __('Trote'),
            self::Alert => __('Alerta'),
            self::Normal => __('Normal'),
            self::Urgent => __('Urgente'),
        };
    }

    /** Ordem no painel / indicadores. */
    public static function orderedForDashboard(): array
    {
        return [
            self::NotCompleted,
            self::Hoax,
            self::Administrative,
            self::Alert,
            self::Normal,
            self::Urgent,
        ];
    }

    /** Ordem dos botões no formulário de ocorrência. */
    public static function orderedForIncidentForm(): array
    {
        return [
            self::NotCompleted,
            self::Hoax,
            self::Administrative,
            self::Alert,
            self::Normal,
            self::Urgent,
        ];
    }

    /** Ordem na fila de despacho do CCO (U → L → N; demais tipos ao final). */
    public function dispatchQueueSortOrder(): int
    {
        return match ($this) {
            self::Urgent => 0,
            self::Alert => 1,
            self::Normal => 2,
            self::Administrative => 3,
            default => 99,
        };
    }

    /** Classes de destaque visual na fila de despacho. */
    public function dispatchQueueAccentClasses(): array
    {
        return match ($this) {
            self::Urgent => [
                'row' => 'border-s-4 border-red-500 bg-red-50/90 dark:border-red-400 dark:bg-red-950/30',
                'badge' => 'border-red-600 bg-red-600 text-white shadow-sm shadow-red-600/30',
                'bar' => 'bg-red-500',
            ],
            self::Normal => [
                'row' => 'border-s-4 border-blue-500 bg-blue-50/80 dark:border-blue-400 dark:bg-blue-950/25',
                'badge' => 'border-blue-600 bg-blue-600 text-white shadow-sm shadow-blue-600/25',
                'bar' => 'bg-blue-500',
            ],
            self::Administrative => [
                'row' => 'border-s-4 border-amber-500 bg-amber-50/85 dark:border-amber-400 dark:bg-amber-950/25',
                'badge' => 'border-amber-600 bg-amber-500 text-white shadow-sm shadow-amber-500/25',
                'bar' => 'bg-amber-500',
            ],
            self::Alert => [
                'row' => 'border-s-4 border-orange-500 bg-orange-50/85 dark:border-orange-400 dark:bg-orange-950/25',
                'badge' => 'border-orange-600 bg-orange-500 text-white shadow-sm shadow-orange-500/25',
                'bar' => 'bg-orange-500',
            ],
            default => [
                'row' => 'border-s-4 border-slate-300 bg-slate-50/70 dark:border-slate-600 dark:bg-slate-900/30',
                'badge' => 'border-slate-400 bg-slate-500 text-white',
                'bar' => 'bg-slate-400',
            ],
        };
    }
}
