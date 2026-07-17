<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Campos específicos de ocorrências de Incêndio Florestal.
 *
 * @see docs/migracao/modalidades-relatorios.md — fire_forest_reports
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fire_forest_reports', function (Blueprint $table): void {
            $table->foreignId('incident_final_report_id')->primary()->constrained('incident_final_reports')->cascadeOnDelete();

            $table->decimal('affected_area_ha', 10, 2)->nullable();
            $table->string('vegetation_type')->nullable();
            $table->string('fire_behavior')->nullable();
            $table->string('probable_cause')->nullable();
            $table->string('discovery_source')->nullable();
            $table->smallInteger('temperature_celsius')->nullable();
            $table->smallInteger('humidity_percent')->nullable();
            $table->smallInteger('wind_speed_kmh')->nullable();
            $table->string('wind_direction')->nullable();
            $table->text('affected_coordinates')->nullable();
            $table->jsonb('vehicles_used')->nullable();
            $table->smallInteger('personnel_count')->nullable();
            $table->boolean('aircraft_used')->default(false);
            $table->text('aircraft_description')->nullable();
            $table->text('external_agencies')->nullable();
            $table->text('actions_taken')->nullable();
            $table->boolean('fauna_damage')->default(false);
            $table->text('fauna_damage_description')->nullable();
            $table->smallInteger('structures_affected')->default(0);
            $table->smallInteger('people_evacuated')->default(0);
            $table->string('final_status')->nullable();
            $table->timestamp('control_achieved_at')->nullable();
            $table->timestamp('extinction_achieved_at')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fire_forest_reports');
    }
};
