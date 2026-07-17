<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Torna `talao` nullable para permitir registros de chamadas simples (C/T/A)
 * sem geração de talão operacional.
 * Em PostgreSQL, múltiplos NULLs são permitidos em colunas UNIQUE.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('incidents', function (Blueprint $table) {
            $table->unsignedInteger('talao')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('incidents', function (Blueprint $table) {
            $table->unsignedInteger('talao')->nullable(false)->change();
        });
    }
};
