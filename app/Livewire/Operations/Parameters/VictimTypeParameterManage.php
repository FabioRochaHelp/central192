<?php

declare(strict_types=1);

namespace App\Livewire\Operations\Parameters;

use App\Models\VictimType;
use Livewire\Attributes\Title;

#[Title('Parâmetros — Tipos de vítima')]
final class VictimTypeParameterManage extends SimpleParameterManage
{
    protected function modelClass(): string
    {
        return VictimType::class;
    }

    protected function heading(): string
    {
        return __('Tipos de vítima');
    }
}
