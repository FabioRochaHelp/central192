<?php

declare(strict_types=1);

namespace App\Livewire\Operations\Parameters;

use App\Models\Procedure;
use Livewire\Attributes\Title;

#[Title('Parâmetros — Procedimentos')]
final class ProcedureParameterManage extends SimpleParameterManage
{
    protected function modelClass(): string
    {
        return Procedure::class;
    }

    protected function heading(): string
    {
        return __('Procedimentos');
    }
}
