<?php

declare(strict_types=1);

namespace App\Livewire\Operations\Parameters;

use App\Models\Accessory;
use Livewire\Attributes\Title;

#[Title('Parâmetros — Acessórios')]
final class AccessoryParameterManage extends SimpleParameterManage
{
    protected function modelClass(): string
    {
        return Accessory::class;
    }

    protected function heading(): string
    {
        return __('Acessórios');
    }
}
