<?php

declare(strict_types=1);

use App\Models\FocoSatelite;
use App\Models\Municipio;
use App\Models\User;
use App\Support\Fire\FireMapFocoQuery;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

uses(TestCase::class);

beforeEach(function (): void {
    config([
        'database.connections.fire_monitor' => [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => true,
        ],
        'fire.dashboard_map_days' => 60,
        'fire.dashboard_map_limit' => 500,
    ]);

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
        $table->decimal('frp', 10, 2)->nullable();
        $table->decimal('temperatura_brilho', 10, 2)->nullable();
        $table->integer('confianca')->nullable();
        $table->decimal('risco_fogo_inpe', 10, 2)->nullable();
        $table->timestamp('data_insercao')->nullable();
        $table->string('status_integracao')->nullable();
    });
});

test('retorna focos recentes com coordenadas para o mapa', function (): void {
    FocoSatelite::query()->create([
        'satelite_id' => 'AQUA_M-T',
        'sensor' => 'VIIRS',
        'latitude' => -15.1,
        'longitude' => -47.9,
        'estado' => 'GO',
        'municipio' => 'Goiânia',
        'data_hora_local' => now()->subHours(2),
        'data_hora_gmt' => now()->subHours(2),
    ]);

    FocoSatelite::query()->create([
        'satelite_id' => 'NOAA-20',
        'latitude' => -16.0,
        'longitude' => -48.0,
        'estado' => 'GO',
        'data_hora_local' => now()->subDays(2),
        'data_hora_gmt' => now()->subDays(2),
    ]);

    FocoSatelite::query()->create([
        'satelite_id' => 'NOAA-21',
        'latitude' => -17.0,
        'longitude' => -49.0,
        'estado' => 'GO',
        'data_hora_local' => now()->subDays(90),
        'data_hora_gmt' => now()->subDays(90),
    ]);

    $results = app(FireMapFocoQuery::class)->forDashboard(null);

    expect($results)->toHaveCount(2)
        ->and($results->first()['municipio'])->toBe('Goiânia')
        ->and($results->first()['lat'])->toBe(-15.1)
        ->and($results->first()['satelite'])->toBe('AQUA_M-T');
});

test('filtra focos pelo estado do municipio do usuario', function (): void {
    $user = new User([
        'municipio_id' => 1,
        'users_type_legacy' => 5,
    ]);
    $user->setRelation('municipio', new Municipio([
        'state' => 'GO',
    ]));

    FocoSatelite::query()->create([
        'satelite_id' => 'NPP-375',
        'latitude' => -16.3,
        'longitude' => -48.9,
        'estado' => 'GO',
        'data_hora_local' => now()->subHour(),
        'data_hora_gmt' => now()->subHour(),
    ]);

    FocoSatelite::query()->create([
        'satelite_id' => 'NPP-375',
        'latitude' => -23.5,
        'longitude' => -46.6,
        'estado' => 'SP',
        'data_hora_local' => now()->subHour(),
        'data_hora_gmt' => now()->subHour(),
    ]);

    $results = app(FireMapFocoQuery::class)->forDashboard($user);

    expect($results)->toHaveCount(1)
        ->and($results->first()['estado'])->toBe('GO');
});

test('filtra focos por periodo informado', function (): void {
    FocoSatelite::query()->create([
        'satelite_id' => 'NOAA-20',
        'latitude' => -15.1,
        'longitude' => -47.9,
        'data_hora_local' => '2026-04-05 10:00:00',
        'data_hora_gmt' => '2026-04-05 13:00:00',
    ]);

    FocoSatelite::query()->create([
        'satelite_id' => 'NOAA-20',
        'latitude' => -16.0,
        'longitude' => -48.0,
        'data_hora_local' => '2026-05-01 10:00:00',
        'data_hora_gmt' => '2026-05-01 13:00:00',
    ]);

    $results = app(FireMapFocoQuery::class)->forDashboard(null, '2026-04-01', '2026-04-30');

    expect($results)->toHaveCount(1)
        ->and($results->first()['lat'])->toBe(-15.1);
});

test('filtra focos por satelite_id', function (): void {
    FocoSatelite::query()->create([
        'satelite_id' => 'NOAA-20',
        'latitude' => -15.1,
        'longitude' => -47.9,
        'data_hora_local' => now()->subHour(),
        'data_hora_gmt' => now()->subHour(),
    ]);

    FocoSatelite::query()->create([
        'satelite_id' => 'NPP-375',
        'latitude' => -16.0,
        'longitude' => -48.0,
        'data_hora_local' => now()->subHour(),
        'data_hora_gmt' => now()->subHour(),
    ]);

    $results = app(FireMapFocoQuery::class)->forDashboard(null, null, null, 'NOAA-20');

    expect($results)->toHaveCount(1)
        ->and($results->first()['satelite'])->toBe('NOAA-20');
});

test('inclui foco sem data_hora_local usando data_hora_gmt', function (): void {
    FocoSatelite::query()->create([
        'satelite_id' => 'GOES-19',
        'latitude' => -15.5,
        'longitude' => -47.5,
        'estado' => 'SP',
        'municipio' => 'Pirapozinho',
        'data_hora_local' => null,
        'data_hora_gmt' => now()->subDays(10),
    ]);

    $results = app(FireMapFocoQuery::class)->forDashboard(null);

    expect($results)->toHaveCount(1)
        ->and($results->first()['municipio'])->toBe('Pirapozinho');
});

test('retorna campos frp e temperatura_brilho no payload do mapa', function (): void {
    FocoSatelite::query()->create([
        'satelite_id' => 'NPP-375',
        'latitude' => -15.1,
        'longitude' => -47.9,
        'data_hora_local' => now()->subHour(),
        'data_hora_gmt' => now()->subHour(),
        'frp' => 3.27,
        'temperatura_brilho' => 316.90,
        'confianca' => 50,
    ]);

    $results = app(FireMapFocoQuery::class)->forDashboard(null);

    expect($results)->toHaveCount(1)
        ->and($results->first()['frp'])->toBe(3.27)
        ->and($results->first()['temperatura_brilho'])->toBe(316.9)
        ->and($results->first()['confianca'])->toBe(50);
});

test('lista satelites disponiveis ordenados', function (): void {
    FocoSatelite::query()->create([
        'satelite_id' => 'NOAA-20',
        'latitude' => -15.1,
        'longitude' => -47.9,
        'data_hora_gmt' => now(),
    ]);

    FocoSatelite::query()->create([
        'satelite_id' => 'AQUA_M-T',
        'latitude' => -16.0,
        'longitude' => -48.0,
        'data_hora_gmt' => now(),
    ]);

    FocoSatelite::query()->create([
        'satelite_id' => 'NOAA-20',
        'latitude' => -17.0,
        'longitude' => -49.0,
        'data_hora_gmt' => now(),
    ]);

    $satellites = app(FireMapFocoQuery::class)->availableSatellites();

    expect($satellites->all())->toBe(['AQUA_M-T', 'NOAA-20']);
});
