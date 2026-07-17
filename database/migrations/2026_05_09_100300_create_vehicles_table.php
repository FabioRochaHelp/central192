<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** @see docs/migracao/entidades.md — viatura (device_id Traccar) */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vehicles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('municipio_id')->constrained('municipios')->cascadeOnDelete();
            $table->string('plate', 32)->nullable();
            $table->string('prefix', 32)->nullable();
            $table->string('make')->nullable();
            $table->string('model')->nullable();
            $table->unsignedSmallInteger('year')->nullable();
            $table->unsignedTinyInteger('status_legacy')->nullable()->comment('Cadastral legado viatura.status');
            $table->string('device_id')->nullable()->index()->comment('Traccar — sem FK local');
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['municipio_id', 'prefix']);
            $table->unique(['municipio_id', 'plate']);
            $table->index(['municipio_id', 'device_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vehicles');
    }
};
