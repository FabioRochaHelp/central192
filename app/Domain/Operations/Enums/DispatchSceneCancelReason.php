<?php

declare(strict_types=1);

namespace App\Domain\Operations\Enums;

enum DispatchSceneCancelReason: string
{
    case CcoOrder = 'cco_order';
    case AddressNotFound = 'address_not_found';
    case NothingFound = 'nothing_found';
    case Hoax = 'hoax';

    public function label(): string
    {
        return match ($this) {
            self::CcoOrder => __('Ordem do CCO'),
            self::AddressNotFound => __('Endereço não localizado'),
            self::NothingFound => __('Nada mais havia'),
            self::Hoax => __('Trote'),
        };
    }
}
