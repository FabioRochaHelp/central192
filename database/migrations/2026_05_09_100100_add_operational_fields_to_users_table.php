<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('municipio_id')->nullable()->after('remember_token')->constrained('municipios')->nullOnDelete();
            $table->foreignId('user_type_id')->nullable()->after('municipio_id')->constrained('user_types')->nullOnDelete();
            $table->unsignedTinyInteger('users_type_legacy')->nullable()->after('user_type_id');
            $table->boolean('active_operational')->default(true)->after('users_type_legacy');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['municipio_id']);
            $table->dropForeign(['user_type_id']);
            $table->dropColumn(['municipio_id', 'user_type_id', 'users_type_legacy', 'active_operational']);
        });
    }
};
