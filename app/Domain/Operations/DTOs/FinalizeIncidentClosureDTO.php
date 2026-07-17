<?php

declare(strict_types=1);

namespace App\Domain\Operations\DTOs;

final readonly class FinalizeIncidentClosureDTO
{
    public function __construct(
        public int $incidentId,
        public int $primaryShiftId,
        public ?int $operatorUserId,
    ) {}
}
