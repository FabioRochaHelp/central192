<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rescue_insect_reports', function (Blueprint $table): void {
            $table->foreignId('incident_final_report_id')->primary()->constrained('incident_final_reports')->cascadeOnDelete();

            $table->string('insect_type')->nullable();          // abelhas, marimbondos, vespas, maribondo_tatu, outro
            $table->string('insect_species')->nullable();
            $table->string('colony_size_estimate')->nullable(); // pequena, media, grande, indeterminada
            $table->string('nest_location_type')->nullable();   // parede_forro, arvore, subsolo, veiculo, caixa_luz_agua, estrutura_metalica, outro
            $table->text('nest_location_detail')->nullable();
            $table->string('technique_used')->nullable();       // captura_realocacao, exterminacao_quimica, exterminacao_fisica, nao_realizado
            $table->string('colony_destination')->nullable();   // apicultor, exterminada, realocada, abandono_local
            $table->smallInteger('people_stung')->default(0);
            $table->string('sting_severity')->nullable();       // sem_atendimento, leve, moderado_hospitalar, grave
            $table->boolean('prehospital_care')->default(false);
            $table->text('prehospital_description')->nullable();
            $table->text('equipment_used')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rescue_insect_reports');
    }
};
