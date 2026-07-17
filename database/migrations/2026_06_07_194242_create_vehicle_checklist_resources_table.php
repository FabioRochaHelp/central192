<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vehicle_checklist_resources', function (Blueprint $table) {
            $table->id();
            $table->foreignId('municipio_id')->constrained('municipios')->cascadeOnDelete();
            $table->string('name');
            $table->string('unit_of_measure', 64);
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['municipio_id', 'name']);
            $table->index(['municipio_id', 'deleted_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vehicle_checklist_resources');
    }
};
