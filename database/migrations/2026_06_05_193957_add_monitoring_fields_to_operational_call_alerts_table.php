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
            $table->foreignId('monitored_by')->nullable()->after('aborted_at')->constrained('users')->nullOnDelete();
            $table->timestamp('monitored_at')->nullable()->after('monitored_by');
        });
    }

    public function down(): void
    {
        Schema::table('operational_call_alerts', function (Blueprint $table) {
            $table->dropConstrainedForeignId('monitored_by');
            $table->dropColumn('monitored_at');
        });
    }
};
