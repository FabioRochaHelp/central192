<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** @see docs/migracao/entidades.md — vitima (núcleo; campos clínicos expandidos em migrações futuras) */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('victims', function (Blueprint $table) {
            $table->id();
            $table->foreignId('municipio_id')->constrained('municipios')->cascadeOnDelete();
            $table->foreignId('incident_id')->constrained('incidents')->cascadeOnDelete();
            $table->string('name')->nullable();
            $table->unsignedTinyInteger('sex')->nullable()->comment('Legado int; alinhar com checks futuros');
            $table->string('rg')->nullable();
            $table->unsignedSmallInteger('age')->nullable();
            $table->string('ssp')->nullable();
            $table->unsignedTinyInteger('situacao')->nullable()->comment('1 atendida 3 recusa legado');
            $table->unsignedTinyInteger('status')->nullable();
            $table->string('hospital')->nullable();
            $table->string('transporte')->nullable();
            $table->string('unidade_saude')->nullable();
            $table->string('medico_us')->nullable();
            $table->string('crm_medico_us')->nullable();
            $table->text('dados_complementares')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['incident_id', 'created_at']);
            $table->index(['municipio_id', 'incident_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('victims');
    }
};
