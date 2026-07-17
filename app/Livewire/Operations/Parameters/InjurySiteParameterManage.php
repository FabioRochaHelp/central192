<?php

declare(strict_types=1);

namespace App\Livewire\Operations\Parameters;

use App\Models\InjurySite;
use Livewire\Attributes\Title;

#[Title('Parâmetros — Locais de ferimento')]
final class InjurySiteParameterManage extends SimpleParameterManage
{
    protected function modelClass(): string
    {
        return InjurySite::class;
    }

    protected function heading(): string
    {
        return __('Locais de ferimento');
    }
}
