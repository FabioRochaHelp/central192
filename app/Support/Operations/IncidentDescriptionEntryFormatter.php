<?php

declare(strict_types=1);

namespace App\Support\Operations;

use App\Models\User;
use Carbon\CarbonInterface;

final class IncidentDescriptionEntryFormatter
{
    public static function formatBlock(User $actor, string $text, ?CarbonInterface $recordedAt = null): string
    {
        $recordedAt ??= now();

        $header = sprintf(
            '[%s] %s',
            $recordedAt->format('d/m/Y H:i:s'),
            $actor->name,
        );

        return "{$header}\n{$text}";
    }
}
