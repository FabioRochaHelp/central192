<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Análise de cicatriz de incêndio (burn scar) — índices espectrais, severidade e
 * área queimada retornados por serviço externo. Pode ser avulsa (por coordenada/período)
 * ou vinculada a uma ocorrência de incêndio florestal.
 *
 * @see .agents/skills/wildfire-scar-analysis/SKILL.md — esquema de saída JSON
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fire_scar_analyses', function (Blueprint $table): void {
            $table->id();

            // Âncora opcional a uma ocorrência (null = análise avulsa por área/foco).
            $table->foreignId('incident_id')->nullable()->constrained('incidents')->nullOnDelete();

            // Localização
            $table->decimal('latitude', 10, 7);
            $table->decimal('longitude', 10, 7);
            $table->string('municipio')->nullable();
            $table->string('estado', 2)->nullable();
            $table->string('bioma')->nullable();

            // Período pré/pós-fogo
            $table->date('pre_fire_date')->nullable();
            $table->date('post_fire_date')->nullable();

            // Fonte de satélite
            $table->string('sensor')->nullable();
            $table->jsonb('bands_used')->nullable();
            $table->unsignedSmallInteger('resolution_m')->nullable();

            // Índices espectrais
            $table->decimal('nbr_pre', 8, 4)->nullable();
            $table->decimal('nbr_post', 8, 4)->nullable();
            $table->decimal('dnbr', 8, 4)->nullable();
            $table->decimal('rbr', 8, 4)->nullable();
            $table->decimal('ndvi_pre', 8, 4)->nullable();
            $table->decimal('ndvi_post', 8, 4)->nullable();
            $table->decimal('bai', 10, 4)->nullable();

            // Severidade de queima
            $table->string('severity_class')->nullable();
            $table->string('dnbr_range')->nullable();
            $table->string('confidence')->nullable();

            // Área queimada
            $table->decimal('area_ha', 12, 2)->nullable();
            $table->decimal('perimeter_km', 12, 2)->nullable();
            $table->jsonb('geometry_geojson')->nullable();

            $table->string('vegetation_type')->nullable();
            $table->text('notes')->nullable();

            // Ciclo de vida
            $table->enum('status', [
                'PENDENTE',
                'PROCESSANDO',
                'CONCLUIDO',
                'ERRO',
            ])->default('PENDENTE');
            $table->string('external_ref')->nullable();
            $table->text('error_message')->nullable();
            $table->timestampTz('requested_at')->nullable();
            $table->timestampTz('completed_at')->nullable();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();

            $table->index('status');
            $table->index('incident_id');
            $table->index('severity_class');
            $table->index('post_fire_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fire_scar_analyses');
    }
};
