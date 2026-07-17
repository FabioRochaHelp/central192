<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adiciona `report_modality` às naturezas — determina qual formulário/tabela de relatório final é usado.
 *
 * @see docs/migracao/modalidades-relatorios.md — Ligação entre Nature e IncidentReportModality
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('natures', function (Blueprint $table): void {
            $table->string('report_modality')->nullable()->after('name');

            $table->index('report_modality');
        });
    }

    public function down(): void
    {
        Schema::table('natures', function (Blueprint $table): void {
            $table->dropIndex(['report_modality']);
            $table->dropColumn('report_modality');
        });
    }
};
