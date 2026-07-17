<?php

declare(strict_types=1);

use App\Domain\Operations\Enums\DispatchStage;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** @see docs/migracao/entidades.md — ocorrencia_has_turnos / docs/banco-dados stage_position */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('incident_dispatches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('municipio_id')->constrained('municipios')->cascadeOnDelete();
            $table->foreignId('incident_id')->constrained('incidents')->cascadeOnDelete();
            $table->foreignId('shift_id')->constrained('shifts')->cascadeOnDelete();
            $table->string('stage')->default(DispatchStage::Dispatched->value);
            $table->unsignedTinyInteger('stage_position')->default(1)->comment('Ordem fixa 1–6 espelhando Kanban');
            $table->timestamps();
            $table->softDeletes();

            $table->index(['municipio_id', 'deleted_at', 'stage']);
            $table->index(['incident_id', 'deleted_at']);
            $table->index(['shift_id', 'deleted_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('incident_dispatches');
    }
};
