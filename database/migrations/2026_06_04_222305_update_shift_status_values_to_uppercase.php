<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/** Renomeia os valores legados 'available'/'assigned' para os novos labels em maiúsculas. */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement("UPDATE shifts SET status = 'DISPONIVEL' WHERE status = 'available'");
        DB::statement("UPDATE shifts SET status = 'EMPENHADO'  WHERE status = 'assigned'");
    }

    public function down(): void
    {
        DB::statement("UPDATE shifts SET status = 'available' WHERE status = 'DISPONIVEL'");
        DB::statement("UPDATE shifts SET status = 'assigned'  WHERE status = 'EMPENHADO'");
    }
};
