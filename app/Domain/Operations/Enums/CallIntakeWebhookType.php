<?php

declare(strict_types=1);

namespace App\Domain\Operations\Enums;

enum CallIntakeWebhookType: string
{
    case Alert = 'alert';
    case Incident = 'incident';

    public function label(): string
    {
        return match ($this) {
            self::Alert => __('Alerta'),
            self::Incident => __('Ocorrência'),
        };
    }
}
