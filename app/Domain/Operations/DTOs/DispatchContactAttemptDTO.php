<?php

declare(strict_types=1);

namespace App\Domain\Operations\DTOs;

/**
 * @param  array<string, mixed>|null  $nearestVehicle
 */
final readonly class DispatchContactAttemptDTO
{
    public function __construct(
        public int $incidentId,
        public int $vehicleId,
        public string $contactMethod,
        public string $contactDetails,
        public bool $successful,
        public ?string $reason,
        public ?array $nearestVehicle,
    ) {}
}
