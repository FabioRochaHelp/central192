<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('victims', function (Blueprint $table): void {
            $table->foreignId('health_unit_id')->nullable()->after('care_local_id')->constrained('health_units')->nullOnDelete();
            $table->decimal('fall_height_meters', 5, 2)->nullable()->after('fall_height');
            $table->unsignedTinyInteger('burn_percentage')->nullable()->after('burn');
            $table->string('pupil_light_reaction', 32)->nullable()->after('pupil_notes');
            $table->string('pupil_symmetry', 32)->nullable()->after('pupil_light_reaction');
            $table->string('pupil_size', 32)->nullable()->after('pupil_symmetry');
            $table->string('pupil_side', 16)->nullable()->after('pupil_size');
        });

        Schema::table('victim_vital_signs', function (Blueprint $table): void {
            $table->unsignedSmallInteger('blood_glucose')->nullable()->after('temperature');
            $table->unsignedTinyInteger('glasgow_eye')->nullable()->after('blood_glucose');
            $table->unsignedTinyInteger('glasgow_verbal')->nullable()->after('glasgow_eye');
            $table->unsignedTinyInteger('glasgow_motor')->nullable()->after('glasgow_verbal');
        });
    }

    public function down(): void
    {
        Schema::table('victim_vital_signs', function (Blueprint $table): void {
            $table->dropColumn([
                'blood_glucose',
                'glasgow_eye',
                'glasgow_verbal',
                'glasgow_motor',
            ]);
        });

        Schema::table('victims', function (Blueprint $table): void {
            $table->dropForeign(['health_unit_id']);
            $table->dropColumn([
                'health_unit_id',
                'fall_height_meters',
                'burn_percentage',
                'pupil_light_reaction',
                'pupil_symmetry',
                'pupil_size',
                'pupil_side',
            ]);
        });
    }
};
