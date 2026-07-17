<?php

declare(strict_types=1);

namespace App\Domain\Operations\Enums;

enum OperationalCallAlertStatus: string
{
    case Pending = 'pending';
    case Monitoring = 'monitoring';
    case Aborted = 'aborted';
    case Converted = 'converted';

    public function label(): string
    {
        return match ($this) {
            self::Pending => __('Pendente'),
            self::Monitoring => __('Monitorando'),
            self::Aborted => __('Abortado'),
            self::Converted => __('Convertido'),
        };
    }
}
