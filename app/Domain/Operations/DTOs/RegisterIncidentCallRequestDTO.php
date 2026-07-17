<?php

declare(strict_types=1);

namespace App\Domain\Operations\DTOs;

final readonly class RegisterIncidentCallRequestDTO
{
    public function __construct(
        public ?string $callerName = null,
        public ?string $callerPhone = null,
        public ?string $notes = null,
        public ?float $latitude = null,
        public ?float $longitude = null,
        public ?int $distanceMeters = null,
        public ?string $pabxUniqueid = null,
    ) {}
}
