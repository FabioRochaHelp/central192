<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('operational_call_alerts', 'temp')) {
            return;
        }

        Schema::table('operational_call_alerts', function (Blueprint $table) {
            $table->dropColumn('temp');
        });
    }

    public function down(): void
    {
        if (Schema::hasColumn('operational_call_alerts', 'temp')) {
            return;
        }

        Schema::table('operational_call_alerts', function (Blueprint $table) {
            $table->decimal('temp', 10, 2)->nullable()->after('caller_name');
        });
    }
};
