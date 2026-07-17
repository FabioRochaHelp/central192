<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('operational_call_alerts', function (Blueprint $table) {
            $table->json('metadata')->nullable()->after('external_reference');
        });
    }

    public function down(): void
    {
        Schema::table('operational_call_alerts', function (Blueprint $table) {
            $table->dropColumn('metadata');
        });
    }
};
