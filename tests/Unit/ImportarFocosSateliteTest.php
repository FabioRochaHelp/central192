<?php

declare(strict_types=1);

use App\Domain\Fire\Actions\ImportarFocosSateliteAction;
use App\Models\Foco;
use App\Models\FocoSatelite;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

uses(TestCase::class);

beforeEach(function (): void {
    config([
        'database.default' => 'sqlite',
        'database.connections.sqlite' => [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => true,
        ],
        'database.connections.fire_monitor' => [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => true,
        ],
    ]);

    Schema::dropIfExists('focos');
    Schema::create('focos', function (Blueprint $table): void {
        $table->id();
        $table->unsignedBigInteger('origem_id')->unique();
        $table->string('satelite')->nullable();
        $table->string('sensor')->nullable();
        $table->string('pais')->nullable();
        $table->string('estado', 2)->nullable();
        $table->string('municipio')->nullable();
        $table->string('bioma')->nullable();
        $table->decimal('latitude', 10, 6);
        $table->decimal('longitude', 10, 6);
        $table->timestamp('data_hora_gmt')->nullable();
        $table->timestamp('data_hora_brasilia')->nullable();
        $table->decimal('risco_fogo', 10, 2)->nullable();
        $table->decimal('temperatura', 10, 2)->nullable();
        $table->integer('confianca')->nullable();
        $table->string('status')->default('PENDENTE');
        $table->timestamp('recebido_em')->nullable();
        $table->timestamps();
    });

    Schema::connection('fire_monitor')->dropIfExists('focos_satelite');
    Schema::connection('fire_monitor')->create('focos_satelite', function (Blueprint $table): void {
        $table->id();
        $table->string('satelite_id')->nullable();
        $table->string('sensor')->nullable();
        $table->string('pais')->nullable();
        $table->decimal('latitude', 10, 6);
        $table->decimal('longitude', 10, 6);
        $table->string('municipio')->nullable();
        $table->string('estado', 2)->nullable();
        $table->string('bioma')->nullable();
        $table->timestamp('data_hora_gmt')->nullable();
        $table->timestamp('data_hora_local')->nullable();
        $table->decimal('risco_fogo_inpe', 10, 2)->nullable();
        $table->decimal('temperatura_brilho', 10, 2)->nullable();
        $table->integer('confianca')->nullable();
        $table->timestamp('data_insercao')->nullable();
        $table->string('status_integracao')->nullable();
    });
});

test('importa foco pendente do satelite para a tabela focos', function (): void {
    $registro = FocoSatelite::query()->create([
        'satelite_id' => 'AQUA_M-T',
        'sensor' => 'VIIRS',
        'pais' => 'Brasil',
        'latitude' => -15.123456,
        'longitude' => -47.654321,
        'municipio' => 'Brasília',
        'estado' => 'DF',
        'bioma' => 'Cerrado',
        'data_hora_gmt' => '2026-05-30 12:00:00',
        'data_hora_local' => '2026-05-30 09:00:00',
        'risco_fogo_inpe' => 0.75,
        'temperatura_brilho' => 320.5,
        'confianca' => 85,
        'data_insercao' => '2026-05-30 09:05:00',
        'status_integracao' => 'PENDENTE',
    ]);

    Artisan::call('focos:importar-satelite');

    expect(Foco::query()->count())->toBe(1);

    $foco = Foco::query()->first();

    expect($foco)->not->toBeNull()
        ->and($foco->origem_id)->toBe($registro->id)
        ->and($foco->satelite)->toBe('AQUA_M-T')
        ->and($foco->municipio)->toBe('Brasília')
        ->and($foco->status)->toBe('PROCESSADO');

    $registro->refresh();

    expect($registro->status_integracao)->toBe('PROCESSADO');
});

test('nao duplica foco ao reimportar o mesmo registro', function (): void {
    FocoSatelite::query()->create([
        'satelite_id' => 'NOAA-20',
        'sensor' => 'VIIRS',
        'latitude' => -10.0,
        'longitude' => -50.0,
        'status_integracao' => 'PENDENTE',
    ]);

    Artisan::call('focos:importar-satelite');
    Artisan::call('focos:importar-satelite');

    expect(Foco::query()->count())->toBe(1);
});

test('reprocessa registros presos em processando', function (): void {
    FocoSatelite::query()->create([
        'satelite_id' => 'NOAA-21',
        'sensor' => 'VIIRS',
        'latitude' => -11.0,
        'longitude' => -51.0,
        'status_integracao' => 'PROCESSANDO',
    ]);

    app(ImportarFocosSateliteAction::class)->execute();

    expect(Foco::query()->count())->toBe(1)
        ->and(FocoSatelite::query()->value('status_integracao'))->toBe('PROCESSADO');
});

test('upsert insere focos em lote no sqlite', function (): void {
    Foco::query()->upsert([
        [
            'origem_id' => 99,
            'latitude' => -10.0,
            'longitude' => -50.0,
            'status' => 'PROCESSADO',
            'created_at' => now()->format('Y-m-d H:i:s'),
            'updated_at' => now()->format('Y-m-d H:i:s'),
        ],
    ], ['origem_id'], ['status', 'updated_at']);

    expect(Foco::query()->count())->toBe(1);
});

test('importa em massa todos os pendentes', function (): void {
    for ($i = 1; $i <= 5; $i++) {
        FocoSatelite::query()->create([
            'satelite_id' => 'NOAA-20',
            'sensor' => 'VIIRS',
            'latitude' => -10.0 - $i,
            'longitude' => -50.0 - $i,
            'status_integracao' => 'PENDENTE',
        ]);
    }

    config(['fire.import_batch_bulk' => 2]);

    $imported = app(ImportarFocosSateliteAction::class)->importAll();

    expect($imported)->toBe(5)
        ->and(Foco::query()->count())->toBe(5)
        ->and(FocoSatelite::query()->where('status_integracao', 'PROCESSADO')->count())->toBe(5);
});
