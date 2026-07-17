<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('incident_dispatches', function (Blueprint $table): void {
            $table->timestamp('dispatched_at')->nullable()->after('stage_position');
            $table->timestamp('departed_base_at')->nullable()->after('dispatched_at');
            $table->timestamp('arrived_scene_at')->nullable()->after('departed_base_at');
            $table->timestamp('left_scene_at')->nullable()->after('arrived_scene_at');
            $table->timestamp('arrived_hospital_at')->nullable()->after('left_scene_at');
            $table->timestamp('released_hospital_at')->nullable()->after('arrived_hospital_at');
            $table->timestamp('returned_base_at')->nullable()->after('released_hospital_at');
            $table->timestamp('released_at')->nullable()->after('returned_base_at');
            $table->timestamp('cancelled_at_scene_at')->nullable()->after('released_at');
            $table->string('scene_cancel_reason', 64)->nullable()->after('cancelled_at_scene_at');
            $table->boolean('is_primary')->nullable()->after('scene_cancel_reason');
        });

        DB::table('incident_dispatches')
            ->orderBy('id')
            ->chunkById(100, function ($rows): void {
                foreach ($rows as $row) {
                    $incident = DB::table('incidents')->where('id', $row->incident_id)->first();
                    if ($incident === null) {
                        continue;
                    }

                    DB::table('incident_dispatches')->where('id', $row->id)->update([
                        'dispatched_at' => $incident->dispatched_at ?? $row->created_at,
                        'departed_base_at' => $incident->departed_base_at,
                        'arrived_scene_at' => $incident->arrived_scene_at,
                        'left_scene_at' => $incident->left_scene_at,
                        'arrived_hospital_at' => $incident->arrived_hospital_at,
                        'released_hospital_at' => $incident->released_hospital_at,
                        'returned_base_at' => $incident->returned_base_at,
                        'released_at' => $row->deleted_at,
                        'is_primary' => $incident->primary_shift_id !== null
                            && (int) $incident->primary_shift_id === (int) $row->shift_id
                            ? true
                            : null,
                    ]);
                }
            });
    }

    public function down(): void
    {
        Schema::table('incident_dispatches', function (Blueprint $table): void {
            $table->dropColumn([
                'dispatched_at',
                'departed_base_at',
                'arrived_scene_at',
                'left_scene_at',
                'arrived_hospital_at',
                'released_hospital_at',
                'returned_base_at',
                'released_at',
                'cancelled_at_scene_at',
                'scene_cancel_reason',
                'is_primary',
            ]);
        });
    }
};
