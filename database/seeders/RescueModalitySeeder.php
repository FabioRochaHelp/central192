<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Nature;
use App\Models\NatureType;
use Illuminate\Database\Seeder;

/** Cria o NatureType "Salvamento" e suas naturezas com report_modality correto. */
class RescueModalitySeeder extends Seeder
{
    public function run(): void
    {
        $rescueType = NatureType::query()->firstOrCreate(['name' => 'Salvamento']);

        $natures = [
            ['name' => 'Animal em situação de risco', 'report_modality' => 'rescue_animal'],
            ['name' => 'Insetos agressivos',           'report_modality' => 'rescue_insects'],
            ['name' => 'Aquático',                     'report_modality' => 'rescue_other'],
            ['name' => 'Em altura',                    'report_modality' => 'rescue_other'],
            ['name' => 'Desencarceramento veicular',   'report_modality' => 'rescue_other'],
            ['name' => 'Colapso estrutural',           'report_modality' => 'rescue_other'],
            ['name' => 'Espaço confinado',             'report_modality' => 'rescue_other'],
            ['name' => 'Elevador',                     'report_modality' => 'rescue_other'],
            ['name' => 'Outro salvamento',             'report_modality' => 'rescue_other'],
        ];

        foreach ($natures as $data) {
            Nature::query()->updateOrCreate(
                ['nature_type_id' => $rescueType->id, 'name' => $data['name']],
                ['report_modality' => $data['report_modality']],
            );
        }
    }
}
