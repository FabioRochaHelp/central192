<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
         Schema::table('municipios', function (Blueprint $table) {

            $table->foreignId('ibge_municipio_id')
                ->nullable()
                ->after('state');

            $table->foreign('ibge_municipio_id')
                ->references('id')
                ->on('ibge_municipios')
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('municipios', function (Blueprint $table) {

            $table->dropForeign(['ibge_municipio_id']);

            $table->dropColumn('ibge_municipio_id');
        });
    }
};
