<?php

declare(strict_types=1);

namespace App\Domain\Operations\Enums;

enum UserLegacyProfile: int
{
    case CentralAdministrator = 1;
    case CentralOperator = 2;
    case Nurse = 3;
    case Doctor = 4;
    case MunicipalOperator = 5;
    case Dispatcher = 6;
    case Attendant = 7;

    public function label(): string
    {
        return match ($this) {
            self::CentralAdministrator => __('Administrador central'),
            self::CentralOperator => __('Operador central'),
            self::Nurse => __('Enfermeiro (relatório pós-ocorrência)'),
            self::Doctor => __('Médico (prescrição)'),
            self::MunicipalOperator => __('Operador municipal'),
            self::Dispatcher => __('Despachador'),
            self::Attendant => __('Atendente'),
        };
    }

    public function isCentral(): bool
    {
        return $this->value <= 2;
    }

    public function hasMultiMunicipioAccess(): bool
    {
        return $this->isCentral() || in_array($this, [self::Dispatcher, self::Attendant], true);
    }

    public function requiresMunicipio(): bool
    {
        return ! $this->hasMultiMunicipioAccess();
    }

    /** @return list<int> */
    public static function values(): array
    {
        return array_map(
            static fn (self $profile): int => $profile->value,
            self::cases(),
        );
    }

    /** @return list<string> */
    public function abilities(): array
    {
        return match ($this) {
            self::CentralAdministrator, self::CentralOperator => ['*'],
            self::Nurse => [
                'dispatch.view',
                'incident.nurse_report',
            ],
            self::Doctor => [
                ...self::municipalOperationalBase(),
                'victim.prescribe',
                'victim.prescription.approve',
                'regulation.view',
                'regulation.regulate',
            ],
            self::MunicipalOperator => self::municipalOperationalBase(),
            self::Dispatcher => [
                'dispatch.view',
                'dispatch.assign_unit',
                'incident.advance_stage',
                'incident.close',
                'incident.create',
            ],
            self::Attendant => [
                'incident.view',
                'incident.create',
            ],
        };
    }

    /** @return list<string> */
    private static function municipalOperationalBase(): array
    {
        return [
            'dispatch.view',
            'dispatch.assign_unit',
            'incident.advance_stage',
            'incident.close',
            'incident.create',
            'catalog.manage',
            'victim.record',
        ];
    }
}
