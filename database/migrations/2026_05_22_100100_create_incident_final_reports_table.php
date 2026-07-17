<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tabela base para relatórios finais CB (Incêndio / Salvamento).
 * Uma sub-tabela específica por modalidade é vinculada por FK ao `id` desta.
 *
 * @see docs/migracao/modalidades-relatorios.md — incident_final_reports
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('incident_final_reports', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('incident_id')->unique()->constrained('incidents')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('modality');
            $table->smallInteger('victims_rescued')->default(0);
            $table->smallInteger('victims_injured')->default(0);
            $table->smallInteger('victims_deceased')->default(0);
            $table->text('resources_summary')->nullable();
            $table->text('external_support')->nullable();
            $table->text('observations')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamps();

            $table->index(['modality', 'submitted_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('incident_final_reports');
    }
};
