<?php

declare(strict_types=1);

namespace App\Domain\Operations\DTOs;

use App\Models\Incident;

final readonly class ReleaseUnitResult
{
    public function __construct(
        public bool $requiresIncidentClosure,
        public Incident $incident,
    ) {}
}
