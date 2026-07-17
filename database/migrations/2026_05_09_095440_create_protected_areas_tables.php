<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** @see docs/migracao/entidades.md — area_protegida, area_protegida_has_contato */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('protected_areas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('municipio_id')->constrained('municipios')->cascadeOnDelete();
            $table->string('name');
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['municipio_id', 'name']);
        });

        Schema::create('protected_area_contacts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('protected_area_id')->constrained('protected_areas')->cascadeOnDelete();
            $table->string('name')->nullable();
            $table->string('phone')->nullable();
            $table->boolean('notify')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('protected_area_contacts');
        Schema::dropIfExists('protected_areas');
    }
};
