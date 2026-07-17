<?php

declare(strict_types=1);

namespace App\Domain\Operations\DTOs;

final readonly class ReleaseUnitDTO
{
    public function __construct(
        public int $incidentId,
        public int $vehicleId,
        public ?int $operatorUserId,
    ) {}
}
