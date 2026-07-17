<?php

declare(strict_types=1);

namespace App\Domain\Operations\DTOs;

final readonly class DispatchUnitDTO
{
    public function __construct(
        public int $incidentId,
        public int $vehicleId,
        public ?string $note,
        public ?int $operatorUserId,
    ) {}
}
