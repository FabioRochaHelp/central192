<?php

declare(strict_types=1);

namespace App\Domain\Operations\Services;

use App\Models\Incident;
use App\Models\IncidentEvent;
use App\Models\User;
use Illuminate\Support\Facades\Auth;

final class IncidentTimelineRecorder
{
    public function record(
        Incident $incident,
        string $eventKey,
        array $payload = [],
        ?User $actor = null,
        string $source = 'web',
    ): IncidentEvent {
        return IncidentEvent::create([
            'municipio_id' => $incident->municipio_id,
            'incident_id' => $incident->id,
            'event_key' => $eventKey,
            'payload' => $payload,
            'actor_id' => $actor?->id ?? Auth::id(),
            'source' => $source,
            'recorded_at' => now(),
        ]);
    }
}
