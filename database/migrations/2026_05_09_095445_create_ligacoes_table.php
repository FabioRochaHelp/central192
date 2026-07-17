<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** @see docs/migracao/entidades.md — ligacoes (classificação C/A/T) */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ligacoes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('municipio_id')->constrained('municipios')->cascadeOnDelete();
            $table->char('tipo', 1);
            $table->text('referencia')->nullable();
            $table->text('descricao')->nullable();
            $table->string('solicitante')->nullable();
            $table->string('numero')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['municipio_id', 'tipo', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ligacoes');
    }
};
