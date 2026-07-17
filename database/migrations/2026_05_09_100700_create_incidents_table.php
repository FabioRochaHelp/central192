<?php

declare(strict_types=1);

use App\Domain\Operations\Enums\IncidentStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** @see docs/migracao/entidades.md — ocorrencia / docs/migracao/banco-dados.md índices */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('incidents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('municipio_id')->nullable()->constrained('municipios')->nullOnDelete();
            $table->unsignedInteger('dispatch_year');
            $table->unsignedInteger('talao');
            $table->string('status')->default(IncidentStatus::Open->value);

            $table->foreignId('nature_id')->nullable()->constrained('natures')->nullOnDelete();
            $table->foreignId('primary_shift_id')->nullable()->constrained('shifts')->nullOnDelete();

            $table->timestampTz('occurred_at')->comment('data da ocorrência (equivalente legacy data)');
            $table->timestampTz('call_received_at')->nullable()->comment('horaChamada');

            $table->string('address_line')->nullable()->comment('endereco');
            $table->string('number')->nullable();
            $table->string('district')->nullable()->comment('bairro');
            $table->string('city')->nullable()->comment('cidade');
            $table->text('reference_notes')->nullable()->comment('referencia');
            $table->text('description')->nullable()->comment('descricao');

            $table->string('caller_name')->nullable()->comment('solicitante');
            $table->string('caller_phone')->nullable()->comment('telefone');
            $table->unsignedTinyInteger('patient_age')->nullable()->comment('idade');
            $table->string('patient_sex', 16)->nullable()->comment('sexo');
            $table->string('patient_name')->nullable()->comment('paciente');
            $table->char('patient_call_type', 1)->nullable()->comment('tipo chamada C/A/T/N/U quando aplicável');

            $table->boolean('is_qta')->default(false)->comment('flag qta legado');
            $table->unsignedSmallInteger('expected_victim_total')->nullable()->comment('totalVitima');
            $table->unsignedSmallInteger('total_death_count')->nullable()->comment('totalObito');

            $table->timestampTz('dispatched_at')->nullable()->comment('horaEmpenho');
            $table->timestampTz('departed_base_at')->nullable()->comment('horaSaida / qti');
            $table->timestampTz('arrived_scene_at')->nullable()->comment('horaLocal');
            $table->timestampTz('left_scene_at')->nullable()->comment('horaSaidaLocal');
            $table->timestampTz('arrived_hospital_at')->nullable()->comment('horaHospital');
            $table->timestampTz('released_hospital_at')->nullable()->comment('horaSaidaHospital');
            $table->timestampTz('returned_base_at')->nullable()->comment('horaBase');

            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();

            $table->foreignId('protected_area_id')->nullable()->constrained('protected_areas')->nullOnDelete();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();
            $table->softDeletes();

            /** Talão único por ano em todo o sistema (base definida só no empenho, quando aplicável). */
            $table->unique(['dispatch_year', 'talao']);
            $table->index(['municipio_id', 'status', 'occurred_at']);
            $table->index(['status', 'occurred_at']);
            $table->index(['primary_shift_id']);
            $table->index(['nature_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('incidents');
    }
};
