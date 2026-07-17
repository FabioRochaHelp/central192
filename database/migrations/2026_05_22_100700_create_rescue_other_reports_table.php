<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rescue_other_reports', function (Blueprint $table): void {
            $table->foreignId('incident_final_report_id')->primary()->constrained('incident_final_reports')->cascadeOnDelete();

            $table->string('rescue_subtype')->nullable();       // aquatico, altura, colapso_estrutural, desencarceramento, espaco_confinado, elevador, outro
            $table->smallInteger('victim_count')->default(1);
            $table->text('situation_description')->nullable();
            $table->string('victim_condition')->nullable();     // ileso, ferido_leve, ferido_grave, obito
            $table->text('entrapment_description')->nullable();
            $table->text('rescue_technique')->nullable();
            $table->text('equipment_used')->nullable();
            $table->boolean('hospital_transport')->default(false);
            $table->string('hospital_name')->nullable();
            $table->string('outcome')->nullable();              // resgatado_ileso, resgatado_ferido, obito_local, nao_localizado
            $table->smallInteger('duration_minutes')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rescue_other_reports');
    }
};
