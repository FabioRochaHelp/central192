<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('municipios', function (Blueprint $table): void {
            $table->string('dispatch_contact_ramal')->nullable()->comment('Ramal para contato pré-despacho');
            $table->string('dispatch_contact_phone')->nullable()->comment('Telefone para contato pré-despacho');
            $table->string('dispatch_contact_whatsapp')->nullable()->comment('WhatsApp para contato pré-despacho');
        });
    }

    public function down(): void
    {
        Schema::table('municipios', function (Blueprint $table): void {
            $table->dropColumn(['dispatch_contact_ramal', 'dispatch_contact_phone', 'dispatch_contact_whatsapp']);
        });
    }
};
