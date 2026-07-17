<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement(
            'ALTER TABLE fire_forest_reports
             ALTER COLUMN external_agencies TYPE jsonb
             USING CASE WHEN external_agencies IS NULL THEN NULL ELSE \'[]\'::jsonb END'
        );
    }

    public function down(): void
    {
        DB::statement(
            'ALTER TABLE fire_forest_reports
             ALTER COLUMN external_agencies TYPE text
             USING external_agencies::text'
        );
    }
};
