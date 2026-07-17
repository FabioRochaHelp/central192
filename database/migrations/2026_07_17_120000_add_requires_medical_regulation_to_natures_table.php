<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Naturezas de saúde (SAMU) exigem regulação médica antes do empenho da viatura.
 * Naturezas sem a flag (Bombeiros/salvamento) seguem direto para a fila de despacho.
 *
 * @see docs/regulacao/plano-implementacao.md
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('natures', function (Blueprint $table): void {
            $table->boolean('requires_medical_regulation')->default(false)->after('report_modality');
        });
    }

    public function down(): void
    {
        Schema::table('natures', function (Blueprint $table): void {
            $table->dropColumn('requires_medical_regulation');
        });
    }
};
