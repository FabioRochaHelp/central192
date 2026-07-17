<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Solicitações adicionais recebidas para uma ocorrência já registrada no mesmo ponto.
 *
 * Uma linha por ligação: o quantitativo exibido na fila de despacho é a contagem
 * destas linhas somada à chamada original que abriu a ocorrência.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('incident_call_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('incident_id')->constrained('incidents')->cascadeOnDelete();
            $table->string('caller_name')->nullable();
            $table->string('caller_phone')->nullable();
            $table->text('notes')->nullable()->comment('Texto anexado à descrição da ocorrência');
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->unsignedInteger('distance_meters')->nullable()->comment('Distância até a ocorrência âncora no momento do registro');
            $table->string('pabx_uniqueid')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['incident_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('incident_call_requests');
    }
};
