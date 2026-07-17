<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** @see docs/migracao/entidades.md — natureza_tipo */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('nature_types', function (Blueprint $table) {
            $table->id();
            $table->foreignId('municipio_id')->nullable()->constrained('municipios')->nullOnDelete();
            $table->string('name');
            $table->timestamps();

            $table->index(['municipio_id', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('nature_types');
    }
};
