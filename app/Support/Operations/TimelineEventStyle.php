<?php

declare(strict_types=1);

namespace App\Support\Operations;

/**
 * Ícone (heroicon) e tom de cor de cada evento da timeline auditável.
 *
 * Retorna apenas tokens semânticos — as classes Tailwind correspondentes ficam
 * no Blade (que é escaneado pelo Tailwind), evitando purga de classes dinâmicas.
 */
final class TimelineEventStyle
{
    /** @return array{icon: string, tone: string} */
    public static function for(string $eventKey): array
    {
        [$icon, $tone] = match ($eventKey) {
            'incident_created' => ['bolt', 'blue'],
            'dispatch_contact_attempted' => ['phone', 'emerald'],
            'dispatch_contact_failed' => ['phone-x-mark', 'amber'],
            'unit_dispatched' => ['truck', 'indigo'],
            'dispatch_stage_advanced' => ['map-pin', 'blue'],
            'dispatch_scene_cancelled' => ['x-circle', 'rose'],
            'unit_released' => ['arrow-uturn-left', 'amber'],
            'incident_closed' => ['check-circle', 'emerald'],
            'incident_cancelled' => ['x-circle', 'rose'],
            'nurse_report_saved' => ['clipboard-document-check', 'teal'],
            'final_report_saved' => ['document-check', 'teal'],
            'victim_recorded' => ['user', 'violet'],
            'prescription_created' => ['beaker', 'violet'],
            'prescription_approved' => ['check-badge', 'emerald'],
            'incident_description_appended' => ['pencil-square', 'zinc'],
            default => ['clock', 'zinc'],
        };

        return ['icon' => $icon, 'tone' => $tone];
    }
}
