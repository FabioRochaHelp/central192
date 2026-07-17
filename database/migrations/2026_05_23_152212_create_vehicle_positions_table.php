<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vehicle_positions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vehicle_id')->constrained('vehicles')->cascadeOnDelete();
            $table->string('device_id')->index()->comment('Traccar device id numérico');
            $table->decimal('latitude', 10, 7);
            $table->decimal('longitude', 10, 7);
            $table->decimal('altitude', 8, 2)->nullable();
            $table->decimal('speed_kmh', 6, 2)->nullable()->comment('Convertido de knots');
            $table->decimal('course', 5, 2)->nullable();
            $table->string('address')->nullable();
            $table->boolean('valid')->default(true);
            $table->timestampTz('fix_time');
            $table->timestampTz('synced_at');

            $table->unique('vehicle_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vehicle_positions');
    }
};
