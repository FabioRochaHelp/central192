<?php

declare(strict_types=1);

namespace App\Livewire\Operations\Parameters;

use App\Models\CareLocal;
use Livewire\Attributes\Title;

#[Title('Parâmetros — Locais')]
final class CareLocalParameterManage extends SimpleParameterManage
{
    protected function modelClass(): string
    {
        return CareLocal::class;
    }

    protected function heading(): string
    {
        return __('Locais');
    }
}
