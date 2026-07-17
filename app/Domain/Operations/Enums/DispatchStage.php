<?php

declare(strict_types=1);

namespace App\Domain\Operations\Enums;

/**
 * Etapas do Kanban / vínculo ocorrência–turno.
 *
 * @see docs/migracao/fluxo-ocorrencias.md
 */
enum DispatchStage: string
{
    case Dispatched = 'dispatched'; // empenhada
    case DepartedBase = 'departed_base'; // qti
    case ArrivedScene = 'arrived_scene'; // local
    case LeftScene = 'left_scene'; // saidaLocal
    case ArrivedHospital = 'arrived_hospital'; // us
    case ReleasedHospital = 'released_hospital'; // saidaUs

    /** Ordem fixa: não voltar, não pular. */
    public static function ordered(): array
    {
        return [
            self::Dispatched,
            self::DepartedBase,
            self::ArrivedScene,
            self::LeftScene,
            self::ArrivedHospital,
            self::ReleasedHospital,
        ];
    }

    public function index(): int
    {
        foreach (self::ordered() as $i => $stage) {
            if ($stage === $this) {
                return $i;
            }
        }

        return -1;
    }

    public function next(): ?self
    {
        $ordered = self::ordered();
        $i = $this->index();

        return $ordered[$i + 1] ?? null;
    }

    /** Rótulos operacionais (Kanban / central). */
    public function label(): string
    {
        return match ($this) {
            self::Dispatched => 'Empenhada',
            self::DepartedBase => 'QTI',
            self::ArrivedScene => 'No local',
            self::LeftScene => 'Saída do local',
            self::ArrivedHospital => 'Na US',
            self::ReleasedHospital => 'Liberada da US',
        };
    }

    public static function tryFromLegacyKanban(?string $legacy): ?self
    {
        if ($legacy === null) {
            return null;
        }

        return match ($legacy) {
            'empenhada' => self::Dispatched,
            'qti' => self::DepartedBase,
            'local' => self::ArrivedScene,
            'saidaLocal' => self::LeftScene,
            'us' => self::ArrivedHospital,
            'saidaUs' => self::ReleasedHospital,
            default => self::tryFrom($legacy),
        };
    }
}
