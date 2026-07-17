<?php

declare(strict_types=1);

namespace App\Domain\Operations\Enums;

enum PrescriptionStatus: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Pending => __('Pendente'),
            self::Approved => __('Aprovada'),
            self::Cancelled => __('Cancelada'),
        };
    }
}
