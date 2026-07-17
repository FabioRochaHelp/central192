<?php

declare(strict_types=1);

namespace App\Domain\Operations\Actions;

use App\Domain\Operations\Events\IncidentDescriptionAppended;
use App\Domain\Operations\Services\IncidentTimelineRecorder;
use App\Models\Incident;
use App\Models\User;
use App\Support\Operations\IncidentDescriptionEntryFormatter;
use Illuminate\Support\Facades\DB;

final class AppendIncidentDescriptionAction
{
    /** Evento da timeline consumido pelo sinal de anotações não vistas no kanban. */
    public const string EVENT_KEY = 'incident_description_appended';

    public function __construct(private readonly IncidentTimelineRecorder $timeline) {}

    public function execute(Incident $incident, string $newText, User $actor): void
    {
        $newText = trim($newText);
        if ($newText === '') {
            return;
        }

        DB::transaction(function () use ($incident, $newText, $actor): void {
            $existing = trim((string) ($incident->description ?? ''));
            $recordedAt = now();
            $block = IncidentDescriptionEntryFormatter::formatBlock($actor, $newText, $recordedAt);
            $description = $existing !== '' ? "{$existing}\n\n{$block}" : $block;

            $incident->update(['description' => $description]);

            $this->timeline->record(
                $incident,
                self::EVENT_KEY,
                [
                    'text' => $newText,
                    'actor_name' => $actor->name,
                    'recorded_at' => $recordedAt->toIso8601String(),
                ],
                $actor,
            );
        });

        IncidentDescriptionAppended::dispatch($incident->fresh());
    }
}
