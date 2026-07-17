<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** @see docs/migracao/entidades.md — natureza */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('natures', function (Blueprint $table) {
            $table->id();
            $table->foreignId('municipio_id')->constrained('municipios')->cascadeOnDelete();
            $table->foreignId('nature_type_id')->nullable()->constrained('nature_types')->nullOnDelete();
            $table->string('name');
            $table->timestamps();

            $table->index(['municipio_id', 'name']);
            $table->index(['municipio_id', 'nature_type_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('natures');
    }
};
