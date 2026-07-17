<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Parâmetros da ocorrência globais (sem municipio_id), conforme decisão de domínio.
 *
 * @see docs/migracao/entidades.md — cadastros auxiliares de vítima/ocorrência
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('victim_types', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->timestamps();
            $table->softDeletes();

            $table->unique('name');
        });

        foreach ([
            ['care_locals', ['municipio_id', 'name']],
            ['injury_sites', ['municipio_id', 'name']],
            ['procedures', ['municipio_id', 'name']],
            ['accessories', ['municipio_id', 'name']],
            ['operational_supports', ['municipio_id', 'name']],
        ] as [$table, $uniqueCols]) {
            Schema::table($table, function (Blueprint $blueprint): void {
                $blueprint->dropForeign(['municipio_id']);
            });
            Schema::table($table, function (Blueprint $blueprint) use ($uniqueCols): void {
                $blueprint->dropUnique($uniqueCols);
                $blueprint->dropColumn('municipio_id');
            });
            Schema::table($table, function (Blueprint $blueprint): void {
                $blueprint->unique('name');
            });
        }

        Schema::table('health_units', function (Blueprint $blueprint): void {
            $blueprint->dropForeign(['municipio_id']);
        });
        Schema::table('health_units', function (Blueprint $blueprint): void {
            $blueprint->dropIndex(['municipio_id', 'name']);
            $blueprint->dropColumn('municipio_id');
        });
        Schema::table('health_units', function (Blueprint $blueprint): void {
            $blueprint->unique('name');
        });

        Schema::table('nature_types', function (Blueprint $blueprint): void {
            $blueprint->dropForeign(['municipio_id']);
        });
        Schema::table('nature_types', function (Blueprint $blueprint): void {
            $blueprint->dropIndex(['municipio_id', 'name']);
            $blueprint->dropColumn('municipio_id');
        });
        Schema::table('nature_types', function (Blueprint $blueprint): void {
            $blueprint->unique('name');
        });

        Schema::table('natures', function (Blueprint $blueprint): void {
            $blueprint->dropForeign(['municipio_id']);
        });
        Schema::table('natures', function (Blueprint $blueprint): void {
            $blueprint->dropIndex(['municipio_id', 'name']);
            $blueprint->dropIndex(['municipio_id', 'nature_type_id']);
            $blueprint->dropColumn('municipio_id');
        });
        Schema::table('natures', function (Blueprint $blueprint): void {
            $blueprint->unique(['nature_type_id', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('victim_types');
    }
};
