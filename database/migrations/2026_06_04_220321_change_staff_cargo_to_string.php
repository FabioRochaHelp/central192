<?php

declare(strict_types=1);

use App\Domain\Operations\Enums\StaffCargo;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Converte a coluna `cargo` de tinyInteger legado para string enum.
 * O valor legado 2 (médico/socorrista) é mapeado para StaffCargo::Socorrista.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('staff', function (Blueprint $table) {
            $table->string('cargo_new', 32)->nullable()->after('cargo');
        });

        // cargo é smallint no PostgreSQL; comparação direta com inteiro funciona
        DB::statement(
            "UPDATE staff SET cargo_new = ? WHERE cargo::integer = 2",
            [StaffCargo::Socorrista->value]
        );

        Schema::table('staff', function (Blueprint $table) {
            $table->dropColumn('cargo');
        });

        Schema::table('staff', function (Blueprint $table) {
            $table->renameColumn('cargo_new', 'cargo');
        });
    }

    public function down(): void
    {
        Schema::table('staff', function (Blueprint $table) {
            $table->unsignedTinyInteger('cargo_legacy')->nullable()->after('cargo');
        });

        DB::statement(
            "UPDATE staff SET cargo_legacy = 2 WHERE cargo = ?",
            [StaffCargo::Socorrista->value]
        );

        Schema::table('staff', function (Blueprint $table) {
            $table->dropColumn('cargo');
        });

        Schema::table('staff', function (Blueprint $table) {
            $table->renameColumn('cargo_legacy', 'cargo');
        });
    }
};
