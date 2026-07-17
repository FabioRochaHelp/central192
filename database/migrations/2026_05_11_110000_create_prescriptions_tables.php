<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('prescriptions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('victim_id')->constrained('victims')->cascadeOnDelete();
            $table->foreignId('medical_staff_id')->nullable()->constrained('staff')->nullOnDelete();
            $table->foreignId('prescribed_by_user_id')->constrained('users')->cascadeOnDelete();
            $table->string('status', 32)->default('pending');
            $table->text('description')->nullable();
            $table->foreignId('approved_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('approved_at')->nullable();
            $table->timestamps();

            $table->index(['victim_id', 'status']);
            $table->index(['medical_staff_id', 'status']);
            $table->index('prescribed_by_user_id');
        });

        Schema::create('prescription_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('prescription_id')->constrained('prescriptions')->cascadeOnDelete();
            $table->unsignedBigInteger('stock_id')->nullable()->comment('Reservado para integração futura com estoque');
            $table->unsignedBigInteger('material_id')->nullable()->comment('Reservado para integração futura com materiais');
            $table->string('medication_name');
            $table->unsignedInteger('quantity');
            $table->timestamps();

            $table->index('prescription_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('prescription_items');
        Schema::dropIfExists('prescriptions');
    }
};
