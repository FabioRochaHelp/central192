<?php

declare(strict_types=1);

namespace App\Domain\Operations\Events;

use App\Models\IncidentFinalReport;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/** Disparado após o relatório final CB ser salvo com sucesso. */
final class FinalReportFilled
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly IncidentFinalReport $report,
    ) {}
}
