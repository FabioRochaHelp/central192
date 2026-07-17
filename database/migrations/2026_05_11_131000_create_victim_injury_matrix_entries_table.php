<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('victim_injury_matrix_entries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('victim_id')->constrained('victims')->cascadeOnDelete();
            $table->string('matrix_region', 64);
            $table->string('matrix_lesion', 128);
            $table->foreignId('injury_site_id')->nullable()->constrained('injury_sites')->nullOnDelete();
            $table->timestamps();

            $table->unique(['victim_id', 'matrix_region', 'matrix_lesion']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('victim_injury_matrix_entries');
    }
};
