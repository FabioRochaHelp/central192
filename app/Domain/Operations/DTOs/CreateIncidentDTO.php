<?php

declare(strict_types=1);

namespace App\Domain\Operations\DTOs;

use App\Domain\Operations\Enums\CallType;
use App\Domain\Operations\Enums\ManchesterRisk;
use Carbon\CarbonInterface;

final readonly class CreateIncidentDTO
{
    public function __construct(
        public ?int $municipioId,
        public ?int $natureId,
        public string $description,
        public ?string $addressLine,
        public ?string $number,
        public ?string $district,
        public ?string $city,
        public ?string $callerName,
        public ?string $callerPhone,
        public ?int $patientAge,
        public ?string $patientSex,
        public ?float $latitude,
        public ?float $longitude,
        public ?string $referenceNotes,
        public CallType $callType,
        public ?int $expectedVictimTotal,
        public ?int $createdByUserId,
        public ?string $patientName = null,
        public ?int $protectedAreaId = null,
        public bool $isQta = false,
        public ?int $totalDeathCount = null,
        public ?CarbonInterface $occurredAt = null,
        public ?CarbonInterface $callReceivedAt = null,
        public ?ManchesterRisk $manchesterRisk = null,
        public ?string $pabxUniqueid = null,
    ) {}
}
