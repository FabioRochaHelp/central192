<?php

declare(strict_types=1);

namespace App\Domain\Operations\DTOs;

use App\Domain\Operations\Enums\DispatchStage;

final readonly class AdvanceDispatchStageDTO
{
    public function __construct(
        public int $incidentDispatchId,
        public DispatchStage $targetStage,
        public ?int $operatorUserId,
    ) {}
}
