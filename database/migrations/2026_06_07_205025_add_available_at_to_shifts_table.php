<?php

declare(strict_types=1);

use App\Domain\Operations\Enums\ShiftStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shifts', function (Blueprint $table) {
            $table->timestampTz('available_at')->nullable()->after('status_legacy');
            $table->index(['municipio_id', 'status', 'available_at']);
        });

        DB::table('shifts')
            ->where('status', ShiftStatus::Disponivel->value)
            ->update([
                'available_at' => DB::raw('COALESCE(updated_at, starts_at, created_at)'),
            ]);
    }

    public function down(): void
    {
        Schema::table('shifts', function (Blueprint $table) {
            $table->dropIndex(['municipio_id', 'status', 'available_at']);
            $table->dropColumn('available_at');
        });
    }
};
