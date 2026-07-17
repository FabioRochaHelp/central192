<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tabelas auxiliares da vítima (docs/migracao/banco-dados.md — vitima_has_*).
 *
 * @see docs/migracao/entidades.md — vitima, sinais, procedimento, acessorio, ferimento
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('victims', function (Blueprint $table): void {
            $table->foreignId('victim_type_id')->nullable()->constrained('victim_types')->nullOnDelete();
            $table->foreignId('care_local_id')->nullable()->constrained('care_locals')->nullOnDelete();

            $table->boolean('fall_height')->nullable()->comment('quedaAltura');
            $table->boolean('halito_etilico')->nullable();
            $table->boolean('burn')->nullable()->comment('queimadura');
            $table->string('vehicle_role', 64)->nullable()->comment('veiculoOcupava');
            $table->string('accident_type', 128)->nullable()->comment('tipoAcidente');
            $table->text('pupil_notes')->nullable();

            $table->string('witness_name')->nullable();
            $table->string('witness_rg')->nullable();
            $table->string('witness_ssp')->nullable();
            $table->string('death_where')->nullable()->comment('obitoOnde');
            $table->text('death_notes')->nullable()->comment('obitoParecer');
        });

        Schema::create('victim_vital_signs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('victim_id')->constrained('victims')->cascadeOnDelete();
            $table->timestampTz('recorded_at');
            $table->unsignedSmallInteger('blood_pressure_systolic')->nullable();
            $table->unsignedSmallInteger('blood_pressure_diastolic')->nullable();
            $table->unsignedSmallInteger('heart_rate')->nullable()->comment('FC bpm');
            $table->unsignedSmallInteger('respiratory_rate')->nullable()->comment('FR mrpm');
            $table->unsignedTinyInteger('spo2')->nullable()->comment('SpO2 %');
            $table->decimal('temperature', 4, 1)->nullable()->comment('°C');
            $table->unsignedTinyInteger('glasgow_total')->nullable()->comment('3–15');
            $table->text('neurological_notes')->nullable();
            $table->string('dominant_side', 1)->nullable()->comment('L ou R');
            $table->timestamps();

            $table->index(['victim_id', 'recorded_at']);
        });

        Schema::create('victim_procedure', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('victim_id')->constrained('victims')->cascadeOnDelete();
            $table->foreignId('procedure_id')->constrained('procedures')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['victim_id', 'procedure_id']);
        });

        Schema::create('victim_accessory', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('victim_id')->constrained('victims')->cascadeOnDelete();
            $table->foreignId('accessory_id')->constrained('accessories')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['victim_id', 'accessory_id']);
        });

        Schema::create('victim_injury_site', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('victim_id')->constrained('victims')->cascadeOnDelete();
            $table->foreignId('injury_site_id')->constrained('injury_sites')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['victim_id', 'injury_site_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('victim_injury_site');
        Schema::dropIfExists('victim_accessory');
        Schema::dropIfExists('victim_procedure');
        Schema::dropIfExists('victim_vital_signs');

        Schema::table('victims', function (Blueprint $table): void {
            $table->dropForeign(['victim_type_id']);
            $table->dropForeign(['care_local_id']);
            $table->dropColumn([
                'victim_type_id',
                'care_local_id',
                'fall_height',
                'halito_etilico',
                'burn',
                'vehicle_role',
                'accident_type',
                'pupil_notes',
                'witness_name',
                'witness_rg',
                'witness_ssp',
                'death_where',
                'death_notes',
            ]);
        });
    }
};
