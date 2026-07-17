<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
       Schema::create('ibge_municipios', function (Blueprint $table) {

            $table->id();

            $table->string('codigo_ibge', 10)->unique();
            $table->string('nome');
            $table->string('uf', 2);

            $table->string('codigo_uf', 2)->nullable();

            $table->decimal('area_km2', 12, 3)->nullable();

            $table->decimal('latitude', 12, 8)->nullable();
            $table->decimal('longitude', 12, 8)->nullable();

            $table->timestamps();
        });

        DB::statement("
            ALTER TABLE ibge_municipios
            ADD COLUMN geometry geometry(MULTIPOLYGON,4674)
        ");

        DB::statement("
            CREATE INDEX ibge_municipios_geometry_idx
            ON ibge_municipios
            USING GIST (geometry)
        ");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS ibge_municipios_geometry_idx');

        Schema::dropIfExists('ibge_municipios');
    }
};
