<?php

declare(strict_types=1);

namespace App\Domain\Operations\Actions;

use App\Domain\Operations\Enums\IncidentStatus;
use App\Domain\Operations\Events\IncidentFinalized;
use App\Domain\Operations\Services\IncidentTimelineRecorder;
use App\Models\Incident;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final class CancelIncidentAction
{
    public function __construct(private readonly IncidentTimelineRecorder $timeline) {}

    public function execute(Incident $incident, string $reason, User $actor): void
    {
        DB::transaction(function () use ($incident, $reason, $actor): void {
            $incident->update(['status' => IncidentStatus::Cancelled]);

            $this->timeline->record(
                $incident,
                'incident_cancelled',
                ['reason' => $reason],
                $actor,
            );
        });

        IncidentFinalized::dispatch($incident->fresh());
    }
}
