<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** @see docs/migracao/plano-migracao-laravel.md — incident_events timeline imutável */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('incident_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('municipio_id')->nullable()->constrained('municipios')->nullOnDelete();
            $table->foreignId('incident_id')->constrained('incidents')->cascadeOnDelete();
            $table->string('event_key');
            $table->json('payload')->nullable();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('source')->default('web');
            $table->timestampTz('recorded_at')->useCurrent();

            $table->index(['incident_id', 'recorded_at']);
            $table->index(['municipio_id', 'recorded_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('incident_events');
    }
};
