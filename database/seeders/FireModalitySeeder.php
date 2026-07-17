<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domain\Operations\Enums\IncidentReportModality;
use App\Models\Nature;
use App\Models\NatureType;
use Illuminate\Database\Seeder;

/**
 * Semente das naturezas de Incêndio — conforme mapeamento em
 * docs/migracao/modalidades-relatorios.md (Ligação entre Nature e IncidentReportModality).
 */
class FireModalitySeeder extends Seeder
{
    public function run(): void
    {
        $fireType = NatureType::query()->firstOrCreate(['name' => 'Incêndio']);

        $natures = [
            ['name' => 'Florestal',             'modality' => IncidentReportModality::FireForest],
            ['name' => 'Residencial',            'modality' => IncidentReportModality::FireBuilding],
            ['name' => 'Comercial',              'modality' => IncidentReportModality::FireBuilding],
            ['name' => 'Industrial',             'modality' => IncidentReportModality::FireBuilding],
            ['name' => 'Veicular',               'modality' => IncidentReportModality::FireBuilding],
        ];

        foreach ($natures as $entry) {
            Nature::query()->updateOrCreate(
                ['nature_type_id' => $fireType->id, 'name' => $entry['name']],
                ['report_modality' => $entry['modality']->value],
            );
        }
    }
}
