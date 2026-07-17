<?php

declare(strict_types=1);

namespace App\Livewire\Operations\Parameters;

use App\Models\OperationalSupport;
use Livewire\Attributes\Title;

#[Title('Parâmetros — Apoio')]
final class OperationalSupportParameterManage extends SimpleParameterManage
{
    protected function modelClass(): string
    {
        return OperationalSupport::class;
    }

    protected function heading(): string
    {
        return __('Apoio');
    }
}
