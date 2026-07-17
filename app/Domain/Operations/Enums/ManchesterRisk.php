<?php

declare(strict_types=1);

namespace App\Domain\Operations\Enums;

enum ManchesterRisk: string
{
    case Red = 'red';
    case Orange = 'orange';
    case Yellow = 'yellow';
    case Green = 'green';
    case Blue = 'blue';

    public function label(): string
    {
        return match ($this) {
            self::Red => __('Vermelho'),
            self::Orange => __('Laranja'),
            self::Yellow => __('Amarelo'),
            self::Green => __('Verde'),
            self::Blue => __('Azul'),
        };
    }

    /** Cor compatível com `flux:badge color="..."`. */
    public function fluxColor(): string
    {
        return $this->value;
    }

    /** Ordem de criticidade (menor = mais crítico). */
    public function sortOrder(): int
    {
        return match ($this) {
            self::Red => 0,
            self::Orange => 1,
            self::Yellow => 2,
            self::Green => 3,
            self::Blue => 4,
        };
    }
}
