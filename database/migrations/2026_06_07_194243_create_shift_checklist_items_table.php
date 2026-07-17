<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shift_checklist_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shift_id')->constrained('shifts')->cascadeOnDelete();
            $table->foreignId('vehicle_checklist_resource_id')
                ->constrained('vehicle_checklist_resources')
                ->cascadeOnDelete();
            $table->unsignedInteger('quantity')->default(0);
            $table->timestamps();

            $table->unique(['shift_id', 'vehicle_checklist_resource_id'], 'shift_checklist_items_shift_resource_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shift_checklist_items');
    }
};
