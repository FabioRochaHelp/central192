<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Momento em que a guarnição foi inteirada das anotações da ocorrência.
 *
 * O balão no kanban conta as anotações feitas depois deste instante (ou depois do
 * empenho, quando nunca houve leitura), sinalizando ao despachador o que ainda
 * não foi repassado à viatura.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('incident_dispatches', function (Blueprint $table) {
            $table->timestampTz('notes_seen_at')->nullable()->after('stage_position');
        });
    }

    public function down(): void
    {
        Schema::table('incident_dispatches', function (Blueprint $table) {
            $table->dropColumn('notes_seen_at');
        });
    }
};
