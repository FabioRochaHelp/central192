<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * @see docs/migracao/entidades.md — local, local_ferimento, procedimento, acessorio, apoio, unidade_atendimento
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('care_locals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('municipio_id')->constrained('municipios')->cascadeOnDelete();
            $table->string('name');
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['municipio_id', 'name']);
        });

        Schema::create('injury_sites', function (Blueprint $table) {
            $table->id();
            $table->foreignId('municipio_id')->constrained('municipios')->cascadeOnDelete();
            $table->string('name');
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['municipio_id', 'name']);
        });

        Schema::create('procedures', function (Blueprint $table) {
            $table->id();
            $table->foreignId('municipio_id')->constrained('municipios')->cascadeOnDelete();
            $table->string('name');
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['municipio_id', 'name']);
        });

        Schema::create('accessories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('municipio_id')->constrained('municipios')->cascadeOnDelete();
            $table->string('name');
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['municipio_id', 'name']);
        });

        Schema::create('operational_supports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('municipio_id')->constrained('municipios')->cascadeOnDelete();
            $table->string('name');
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['municipio_id', 'name']);
        });

        Schema::create('health_units', function (Blueprint $table) {
            $table->id();
            $table->foreignId('municipio_id')->constrained('municipios')->cascadeOnDelete();
            $table->string('name');
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['municipio_id', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('health_units');
        Schema::dropIfExists('operational_supports');
        Schema::dropIfExists('accessories');
        Schema::dropIfExists('procedures');
        Schema::dropIfExists('injury_sites');
        Schema::dropIfExists('care_locals');
    }
};
