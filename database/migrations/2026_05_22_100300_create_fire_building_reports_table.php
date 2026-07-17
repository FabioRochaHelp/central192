<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Campos específicos de ocorrências de Incêndio em Edificação (residencial / comercial / industrial).
 *
 * @see docs/migracao/modalidades-relatorios.md — fire_building_reports
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fire_building_reports', function (Blueprint $table): void {
            $table->foreignId('incident_final_report_id')->primary()->constrained('incident_final_reports')->cascadeOnDelete();

            $table->string('building_type')->nullable();
            $table->string('construction_type')->nullable();
            $table->smallInteger('floors_total')->nullable();
            $table->smallInteger('floors_affected')->nullable();
            $table->decimal('affected_area_m2', 8, 2)->nullable();
            $table->json('rooms_affected')->nullable();
            $table->string('probable_cause')->nullable();
            $table->string('fire_origin_location')->nullable();
            $table->boolean('hazmat_present')->default(false);
            $table->text('hazmat_description')->nullable();
            $table->smallInteger('occupants_at_incident')->nullable();
            $table->smallInteger('animals_rescued')->default(0);
            $table->smallInteger('animals_deceased')->default(0);
            $table->smallInteger('residents_displaced')->default(0);
            $table->string('damage_level')->nullable();
            $table->boolean('vehicle_involved')->default(false);
            $table->text('external_agencies')->nullable();
            $table->text('actions_taken')->nullable();
            $table->string('final_status')->nullable();
            $table->string('business_name')->nullable();
            $table->string('business_activity')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fire_building_reports');
    }
};
