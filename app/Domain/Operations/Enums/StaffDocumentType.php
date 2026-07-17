<?php

declare(strict_types=1);

namespace App\Domain\Operations\Enums;

enum StaffDocumentType: string
{
    case Rg = 'RG';
    case Ctps = 'CTPS';
    case Cin = 'CIN';
    case Cnh = 'CNH';
    case Crea = 'CREA';

    public function label(): string
    {
        return match ($this) {
            self::Rg => 'RG — Registro Geral',
            self::Ctps => 'CTPS — Carteira de Trabalho',
            self::Cin => 'CIN — Carteira de Identidade Nacional',
            self::Cnh => 'CNH — Carteira Nacional de Habilitação',
            self::Crea => 'CREA — Conselho Regional de Engenharia',
        };
    }
}
