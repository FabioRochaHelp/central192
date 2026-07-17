<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\Operations\Support\InjuryMatrixDefinition;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/** Local de ferimento — cadastro global (vínculo em victim_injury_site + matriz corporal). */
class InjurySite extends Model
{
    use SoftDeletes;

    protected $table = 'injury_sites';

    protected $fillable = [
        'name',
        'matrix_region',
        'matrix_lesion',
    ];

    protected static function booted(): void
    {
        static::saving(function (InjurySite $site): void {
            if ($site->matrix_region !== null && $site->matrix_lesion !== null) {
                return;
            }

            $inferred = InjuryMatrixDefinition::inferMatrixFromName((string) $site->name);
            if ($inferred['matrix_region'] !== null) {
                $site->matrix_region = $inferred['matrix_region'];
                $site->matrix_lesion = $inferred['matrix_lesion'];
            }
        });
    }
}
