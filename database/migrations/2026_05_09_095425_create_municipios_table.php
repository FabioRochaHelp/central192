<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** @see docs/migracao/banco-dados.md — contract → municipios */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('municipios', function (Blueprint $table) {
            $table->id();
            $table->string('razao_social');
            $table->string('cnpj')->nullable()->unique();
            $table->string('ie')->nullable()->unique();
            $table->string('phone')->nullable()->unique();
            $table->string('zipcode')->nullable();
            $table->string('address')->nullable();
            $table->string('number')->nullable();
            $table->string('district')->nullable();
            $table->string('city')->nullable();
            $table->string('state', 4)->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['active', 'city']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('municipios');
    }
};
