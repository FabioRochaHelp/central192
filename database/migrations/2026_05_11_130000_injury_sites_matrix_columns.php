<?php

declare(strict_types=1);

use App\Domain\Operations\Support\InjuryMatrixDefinition;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('injury_sites', function (Blueprint $table): void {
            $table->string('matrix_region', 64)->nullable()->after('name');
            $table->string('matrix_lesion', 128)->nullable()->after('matrix_region');
            $table->index(['matrix_region', 'matrix_lesion']);
        });

        $regions = InjuryMatrixDefinition::BODY_REGIONS;
        $lesions = InjuryMatrixDefinition::LESION_TYPES;

        $sites = DB::table('injury_sites')->select(['id', 'name'])->get();

        foreach ($sites as $site) {
            $trimmed = trim((string) $site->name);
            if ($trimmed === '' || ! preg_match('/^(.+?)\s*-\s*(.+)$/u', $trimmed, $m)) {
                continue;
            }

            $region = trim($m[1]);
            $lesion = trim($m[2]);

            if (! in_array($region, $regions, true) || ! in_array($lesion, $lesions, true)) {
                continue;
            }

            DB::table('injury_sites')->where('id', $site->id)->update([
                'matrix_region' => $region,
                'matrix_lesion' => $lesion,
            ]);
        }
    }

    public function down(): void
    {
        Schema::table('injury_sites', function (Blueprint $table): void {
            $table->dropIndex(['matrix_region', 'matrix_lesion']);
            $table->dropColumn(['matrix_region', 'matrix_lesion']);
        });
    }
};
