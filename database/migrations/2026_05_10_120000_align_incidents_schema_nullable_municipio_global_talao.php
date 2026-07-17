<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Bancos que já rodaram `2026_05_09_100700` antes da alteração nos arquivos mantêm
 * `municipio_id` NOT NULL e índice único antigo — quebra criação sem base e eventos da timeline.
 *
 * Também reenumera talões por ano quando o índice antigo ainda existe, para permitir `unique(dispatch_year, talao)` global.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('incidents') || ! Schema::hasTable('incident_events')) {
            return;
        }

        $driver = Schema::getConnection()->getDriverName();

        match ($driver) {
            'pgsql' => $this->upgradePostgreSql(),
            'mysql' => $this->upgradeMySql(),
            default => null,
        };
    }

    public function down(): void
    {
        //
    }

    private function upgradePostgreSql(): void
    {
        $incidentsNullable = $this->pgsqlColumnIsNullable('incidents', 'municipio_id');
        $eventsNullable = $this->pgsqlColumnIsNullable('incident_events', 'municipio_id');

        if ($incidentsNullable && $eventsNullable) {
            return;
        }

        if (! $incidentsNullable) {
            $hasOldUnique = DB::selectOne(
                'SELECT 1 FROM pg_indexes WHERE tablename = ? AND indexname = ?',
                ['incidents', 'incidents_municipio_id_dispatch_year_talao_unique'],
            );

            if ($hasOldUnique !== null) {
                DB::statement('ALTER TABLE incidents DROP CONSTRAINT incidents_municipio_id_dispatch_year_talao_unique');

                foreach ($this->distinctDispatchYears() as $year) {
                    $ids = DB::table('incidents')
                        ->where('dispatch_year', $year)
                        ->orderBy('id')
                        ->pluck('id');

                    foreach ($ids as $index => $id) {
                        DB::table('incidents')->where('id', $id)->update(['talao' => $index + 1]);
                    }
                }
            }

            DB::statement('ALTER TABLE incidents DROP CONSTRAINT IF EXISTS incidents_municipio_id_foreign');
            DB::statement('ALTER TABLE incidents ALTER COLUMN municipio_id DROP NOT NULL');

            if (! $this->pgsqlFkExists('incidents_municipio_id_foreign')) {
                DB::statement(
                    'ALTER TABLE incidents ADD CONSTRAINT incidents_municipio_id_foreign FOREIGN KEY (municipio_id) REFERENCES municipios(id) ON DELETE SET NULL',
                );
            }

            $hasNewUnique = DB::selectOne(
                'SELECT 1 FROM pg_indexes WHERE tablename = ? AND indexname = ?',
                ['incidents', 'incidents_dispatch_year_talao_unique'],
            );

            if ($hasNewUnique === null) {
                Schema::table('incidents', function (Blueprint $table): void {
                    $table->unique(['dispatch_year', 'talao']);
                });
            }
        }

        if (! $eventsNullable) {
            DB::statement('ALTER TABLE incident_events DROP CONSTRAINT IF EXISTS incident_events_municipio_id_foreign');
            DB::statement('ALTER TABLE incident_events ALTER COLUMN municipio_id DROP NOT NULL');

            if (! $this->pgsqlFkExists('incident_events_municipio_id_foreign')) {
                DB::statement(
                    'ALTER TABLE incident_events ADD CONSTRAINT incident_events_municipio_id_foreign FOREIGN KEY (municipio_id) REFERENCES municipios(id) ON DELETE SET NULL',
                );
            }
        }
    }

    private function pgsqlColumnIsNullable(string $table, string $column): bool
    {
        $row = DB::selectOne(
            'SELECT is_nullable FROM information_schema.columns WHERE table_schema = current_schema() AND table_name = ? AND column_name = ?',
            [$table, $column],
        );

        return $row !== null && $row->is_nullable === 'YES';
    }

    private function pgsqlFkExists(string $constraintName): bool
    {
        return DB::selectOne(
            'SELECT 1 FROM pg_constraint WHERE conname = ?',
            [$constraintName],
        ) !== null;
    }

    private function upgradeMySql(): void
    {
        $db = DB::getDatabaseName();

        $incidentsNullable = $this->mysqlColumnIsNullable($db, 'incidents', 'municipio_id');
        $eventsNullable = $this->mysqlColumnIsNullable($db, 'incident_events', 'municipio_id');

        if ($incidentsNullable && $eventsNullable) {
            return;
        }

        if (! $incidentsNullable) {
            Schema::table('incidents', function (Blueprint $table): void {
                $table->dropForeign(['municipio_id']);
                $table->dropUnique(['municipio_id', 'dispatch_year', 'talao']);
            });

            foreach ($this->distinctDispatchYears() as $year) {
                $ids = DB::table('incidents')
                    ->where('dispatch_year', $year)
                    ->orderBy('id')
                    ->pluck('id');

                foreach ($ids as $index => $id) {
                    DB::table('incidents')->where('id', $id)->update(['talao' => $index + 1]);
                }
            }

            DB::statement('ALTER TABLE incidents MODIFY municipio_id BIGINT UNSIGNED NULL');

            Schema::table('incidents', function (Blueprint $table): void {
                $table->foreign('municipio_id')->references('id')->on('municipios')->nullOnDelete();
                $table->unique(['dispatch_year', 'talao']);
            });
        }

        if (! $eventsNullable) {
            Schema::table('incident_events', function (Blueprint $table): void {
                $table->dropForeign(['municipio_id']);
            });

            DB::statement('ALTER TABLE incident_events MODIFY municipio_id BIGINT UNSIGNED NULL');

            Schema::table('incident_events', function (Blueprint $table): void {
                $table->foreign('municipio_id')->references('id')->on('municipios')->nullOnDelete();
            });
        }
    }

    private function mysqlColumnIsNullable(string $database, string $table, string $column): bool
    {
        $row = DB::selectOne(
            'SELECT IS_NULLABLE AS is_nullable FROM information_schema.columns WHERE table_schema = ? AND table_name = ? AND column_name = ?',
            [$database, $table, $column],
        );

        return $row !== null && strtoupper((string) $row->is_nullable) === 'YES';
    }

    /** @return list<int|string> */
    private function distinctDispatchYears(): array
    {
        return DB::table('incidents')->distinct()->orderBy('dispatch_year')->pluck('dispatch_year')->all();
    }
};
