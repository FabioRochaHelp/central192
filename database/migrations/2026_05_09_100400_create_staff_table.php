<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** @see docs/migracao/entidades.md — efetivo */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('staff', function (Blueprint $table) {
            $table->id();
            $table->foreignId('municipio_id')->constrained('municipios')->cascadeOnDelete();
            $table->string('name');
            $table->string('document_type')->nullable();
            $table->string('document_number')->nullable();
            $table->string('cpf', 14)->nullable()->index();
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->unsignedTinyInteger('cargo')->nullable()->comment('Legado cargo; 2 = médico prescrição');
            $table->timestamps();
            $table->softDeletes();

            $table->index(['municipio_id', 'cargo']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('staff');
    }
};
