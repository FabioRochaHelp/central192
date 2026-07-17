<?php

declare(strict_types=1);

namespace App\Domain\Operations\Enums;

enum StaffCargo: string
{
    case Motorista = 'MOTORISTA';
    case Encarregado = 'ENCARREGADO';
    case Administrador = 'ADMINISTRADOR';
    case Brigadista = 'BRIGADISTA';
    case Socorrista = 'SOCORRISTA';
    case Diretor = 'DIRETOR';
    case Gerente = 'GERENTE';

    public function label(): string
    {
        return match ($this) {
            self::Motorista => 'Motorista',
            self::Encarregado => 'Encarregado',
            self::Administrador => 'Administrador',
            self::Brigadista => 'Brigadista',
            self::Socorrista => 'Socorrista',
            self::Diretor => 'Diretor',
            self::Gerente => 'Gerente',
        };
    }
}
