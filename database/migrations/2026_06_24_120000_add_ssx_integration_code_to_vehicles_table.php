<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Vínculo do veículo com a unidade rastreada no SSX/SystemSatX. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vehicles', function (Blueprint $table) {
            $table->string('ssx_integration_code')->nullable()->after('device_id')
                ->index()->comment('SSX TrackedUnitIntegrationCode — sem FK local');
        });
    }

    public function down(): void
    {
        Schema::table('vehicles', function (Blueprint $table) {
            $table->dropColumn('ssx_integration_code');
        });
    }
};
