<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Identificador da chamada no PABX (Asterisk ${UNIQUEID}). Usado para montar a URL
 * da gravação reproduzida no painel via proxy autenticado.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('incidents', function (Blueprint $table): void {
            $table->string('pabx_uniqueid', 128)->nullable()->after('caller_phone');
        });

        Schema::table('operational_call_alerts', function (Blueprint $table): void {
            $table->string('pabx_uniqueid', 128)->nullable()->after('external_reference');
        });
    }

    public function down(): void
    {
        Schema::table('incidents', function (Blueprint $table): void {
            $table->dropColumn('pabx_uniqueid');
        });

        Schema::table('operational_call_alerts', function (Blueprint $table): void {
            $table->dropColumn('pabx_uniqueid');
        });
    }
};
