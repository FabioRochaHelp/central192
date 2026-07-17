<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('operational_call_alerts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('phone', 32);
            $table->string('caller_name')->nullable();
            $table->decimal('latitude', 10, 7);
            $table->decimal('longitude', 10, 7);
            $table->timestamp('call_received_at')->nullable();
            $table->string('external_reference', 500)->nullable();
            $table->text('form_url');
            $table->timestamp('expires_at');
            $table->string('status', 20)->default('pending');
            $table->foreignId('converted_incident_id')->nullable()->constrained('incidents')->nullOnDelete();
            $table->foreignId('aborted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('aborted_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'expires_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('operational_call_alerts');
    }
};
