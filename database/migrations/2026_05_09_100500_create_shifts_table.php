<?php

declare(strict_types=1);

use App\Domain\Operations\Enums\ShiftStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** @see docs/migracao/entidades.md — turno (inicio/final/status operacional) */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shifts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('municipio_id')->constrained('municipios')->cascadeOnDelete();
            $table->foreignId('vehicle_id')->constrained()->cascadeOnDelete();
            $table->dateTime('starts_at');
            $table->dateTime('ends_at');
            $table->string('status')->default(ShiftStatus::Disponivel->value);
            $table->unsignedTinyInteger('status_legacy')->nullable()->comment('1 disponível 2 empenhado legado');
            $table->timestamps();

            $table->index(['municipio_id', 'status', 'ends_at']);
            $table->index(['vehicle_id', 'ends_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shifts');
    }
};
