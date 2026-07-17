<?php

declare(strict_types=1);

namespace App\Support\Operations;

/** Rótulos curtos para chaves da timeline auditável. */
final class TimelineEventLabels
{
    public static function for(string $key): string
    {
        return match ($key) {
            'incident_created' => 'Ocorrência registrada',
            'unit_dispatched' => 'Equipe empenhada',
            'dispatch_contact_attempted' => 'Contato pré-despacho registrado',
            'dispatch_contact_failed' => 'Contato pré-despacho falhou',
            'dispatch_stage_advanced' => 'Etapa do deslocamento',
            'dispatch_scene_cancelled' => 'Cancelado no local',
            'unit_released' => 'Retorno à base (pendente relatório de enfermagem)',
            'incident_closed' => 'Ocorrência encerrada',
            'incident_cancelled' => 'Ocorrência cancelada',
            'nurse_report_saved' => 'Relatório de enfermagem registrado',
            'final_report_saved' => 'Relatório final registrado',
            'victim_recorded' => 'Registro de vítima atualizado',
            'prescription_created' => 'Prescrição médica criada',
            'prescription_approved' => 'Prescrição médica aprovada',
            'incident_description_appended' => 'Descrição complementada',
            default => str_replace('_', ' ', $key),
        };
    }
}
