<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Garante que `natures` não usa mais `municipio_id` (cadastro global).
 * Idempotente: corrige bases onde a migração anterior não concluiu esta etapa.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('natures')) {
            return;
        }

        if (Schema::hasColumn('natures', 'municipio_id')) {
            Schema::table('natures', function (Blueprint $blueprint): void {
                $blueprint->dropForeign(['municipio_id']);
            });

            Schema::table('natures', function (Blueprint $blueprint): void {
                $blueprint->dropIndex(['municipio_id', 'name']);
                $blueprint->dropIndex(['municipio_id', 'nature_type_id']);
                $blueprint->dropColumn('municipio_id');
            });
        }

        $this->ensureUniqueNatureTypeAndName();
    }

    public function down(): void
    {
        //
    }

    private function ensureUniqueNatureTypeAndName(): void
    {
        $indexes = Schema::getIndexes('natures');

        foreach ($indexes as $index) {
            $columns = $index['columns'] ?? [];
            if (($index['unique'] ?? false) && $columns === ['nature_type_id', 'name']) {
                return;
            }
        }

        Schema::table('natures', function (Blueprint $blueprint): void {
            $blueprint->unique(['nature_type_id', 'name']);
        });
    }
};
