<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shift_checklist_items', function (Blueprint $table) {
            $table->unsignedInteger('quantity')->default(0)->change();
        });
    }

    public function down(): void
    {
        Schema::table('shift_checklist_items', function (Blueprint $table) {
            $table->decimal('quantity', 10, 2)->default(0)->change();
        });
    }
};
