<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('focos', function (Blueprint $table) {

            $table->id();

            // ID do registro na tabela focos_satelite
            $table->unsignedBigInteger('origem_id')->unique();

            $table->string('satelite')->nullable();
            $table->string('sensor')->nullable();

            $table->string('pais')->nullable();

            $table->string('estado', 2)->nullable();

            $table->string('municipio')->nullable();

            $table->string('bioma')->nullable();

            $table->decimal('latitude', 10, 6);

            $table->decimal('longitude', 10, 6);

            $table->timestamp('data_hora_gmt')->nullable();

            $table->timestamp('data_hora_brasilia')->nullable();

            $table->decimal('risco_fogo', 10, 2)->nullable();

            $table->decimal('temperatura', 10, 2)->nullable();

            $table->integer('confianca')->nullable();

            // Controle operacional do Hub
            $table->enum('status', [
                'PENDENTE',
                'PROCESSANDO',
                'PROCESSADO',
                'ERRO',
            ])->default('PENDENTE');

            $table->timestamp('recebido_em')->nullable();

            $table->timestamps();

            $table->index('status');
            $table->index('estado');
            $table->index('municipio');
            $table->index('data_hora_brasilia');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('focos');
    }
};
