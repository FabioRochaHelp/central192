<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shifts', function (Blueprint $table) {
            $table->dateTime('checklist_completed_at')->nullable()->after('status_legacy');
            $table->text('checklist_observation')->nullable()->after('checklist_completed_at');
        });
    }

    public function down(): void
    {
        Schema::table('shifts', function (Blueprint $table) {
            $table->dropColumn(['checklist_completed_at', 'checklist_observation']);
        });
    }
};
