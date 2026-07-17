<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rescue_animal_reports', function (Blueprint $table): void {
            $table->foreignId('incident_final_report_id')->primary()->constrained('incident_final_reports')->cascadeOnDelete();

            $table->string('animal_category')->nullable();   // doméstico, silvestre, de_producao
            $table->string('animal_species')->nullable();    // cão, gato, cavalo, boi, serpente, ave, jacaré, outro
            $table->string('animal_breed')->nullable();
            $table->string('animal_size')->nullable();       // pequeno, médio, grande
            $table->string('entrapment_type')->nullable();   // árvore, buraco, cisterna_poco, via_aquatica, veículo, estrutura, cerca_cabo, elevado, outro
            $table->smallInteger('entrapment_height_m')->nullable();
            $table->string('animal_condition_arrival')->nullable(); // calmo, agitado, ferido, inconsciente, obito_chegada
            $table->text('equipment_used')->nullable();
            $table->string('outcome')->nullable();           // resgatado_tutor, resgatado_abrigo, resgatado_veterinario, solto_silvestre, nao_localizado, obito
            $table->string('owner_name')->nullable();
            $table->string('owner_phone')->nullable();
            $table->text('destination_notes')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rescue_animal_reports');
    }
};
