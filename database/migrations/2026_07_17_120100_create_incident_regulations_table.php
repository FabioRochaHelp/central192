<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Registro da regulação médica de uma ocorrência (um por ocorrência).
 * Guarda a decisão do médico regulador, prioridade, hipótese e tempos-resposta.
 *
 * @see docs/regulacao/plano-implementacao.md
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('incident_regulations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('municipio_id')->nullable()->constrained('municipios')->nullOnDelete();
            $table->foreignId('incident_id')->unique()->constrained('incidents')->cascadeOnDelete();
            $table->foreignId('regulator_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 32)->nullable();            // RegulationDecision
            $table->string('priority', 16)->nullable();          // ManchesterRisk
            $table->text('diagnostic_hypothesis')->nullable();
            $table->string('recommended_resource', 16)->nullable(); // RegulationResource
            $table->text('guidance_notes')->nullable();
            $table->text('refusal_reason')->nullable();
            $table->string('transfer_target')->nullable();
            $table->timestamp('assumed_at')->nullable();
            $table->timestamp('decided_at')->nullable();
            $table->unsignedInteger('response_time_seconds')->nullable();
            $table->timestamps();

            $table->index('regulator_user_id');
            $table->index('status');
            $table->index(['municipio_id', 'decided_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('incident_regulations');
    }
};
