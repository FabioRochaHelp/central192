<?php

declare(strict_types=1);

namespace App\Domain\Operations\Actions;

use App\Domain\Operations\Support\InjuryMatrixDefinition;
use App\Models\InjurySite;

/**
 * Garante todas as combinações Região × Tipo da matriz em injury_sites (nome canônico "Região - Tipo").
 * Idempotente; corrige cadastros antigos sem matrix_region/matrix_lesion e restaura soft-deletes do padrão.
 */
final class SyncStandardInjuryMatrixSitesAction
{
    public function execute(): void
    {
        foreach (InjuryMatrixDefinition::BODY_REGIONS as $region) {
            foreach (InjuryMatrixDefinition::LESION_TYPES as $lesion) {
                $name = $region.' - '.$lesion;
                $site = InjurySite::withTrashed()->where('name', $name)->first();

                if ($site === null) {
                    InjurySite::query()->create([
                        'name' => $name,
                        'matrix_region' => $region,
                        'matrix_lesion' => $lesion,
                    ]);

                    continue;
                }

                $site->forceFill([
                    'matrix_region' => $region,
                    'matrix_lesion' => $lesion,
                ]);

                if ($site->trashed()) {
                    $site->restore();
                }

                $site->save();
            }
        }
    }
}
