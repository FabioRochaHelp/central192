<?php

declare(strict_types=1);

namespace App\Domain\Operations\DTOs;

use App\Domain\Operations\Enums\ManchesterRisk;
use App\Domain\Operations\Enums\RegulationDecision;
use App\Domain\Operations\Enums\RegulationResource;

final readonly class RegulationDecisionDTO
{
    public function __construct(
        public int $incidentId,
        public int $regulatorUserId,
        public RegulationDecision $decision,
        public ?ManchesterRisk $priority = null,
        public ?string $diagnosticHypothesis = null,
        public ?RegulationResource $recommendedResource = null,
        public ?string $guidanceNotes = null,
        public ?string $refusalReason = null,
        public ?string $transferTarget = null,
    ) {}
}
