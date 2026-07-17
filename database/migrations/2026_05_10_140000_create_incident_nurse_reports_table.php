<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Relatório assistencial do enfermeiro após encerramento da ocorrência.
 *
 * @see docs/migracao/controllers-models.md — controller Relatorio (entrada de relatórios)
 * @see docs/migracao/fluxo-ocorrencias.md — encerramento operacional
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('incident_nurse_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('incident_id')->constrained('incidents')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->text('clinical_evolution')->comment('Evolução / relatório assistencial');
            $table->text('conduct_summary')->nullable()->comment('Conduta e intervenções relevantes');
            $table->text('destination_notes')->nullable()->comment('Destino US / encaminhamento');
            $table->timestampTz('submitted_at')->useCurrent();
            $table->timestamps();

            $table->unique('incident_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('incident_nurse_reports');
    }
};
