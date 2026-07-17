<?php

declare(strict_types=1);

use App\Livewire\Dashboard;
use App\Livewire\FireFocosMap;
use App\Models\FocoSatelite;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
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
    ]);

    Schema::connection('fire_monitor')->dropIfExists('focos_satelite');
    Schema::connection('fire_monitor')->create('focos_satelite', function (Blueprint $table): void {
        $table->id();
        $table->string('satelite_id')->nullable();
        $table->string('sensor')->nullable();
        $table->decimal('latitude', 10, 6);
        $table->decimal('longitude', 10, 6);
        $table->string('municipio')->nullable();
        $table->string('estado', 2)->nullable();
        $table->timestamp('data_hora_gmt')->nullable();
        $table->timestamp('data_hora_local')->nullable();
        $table->decimal('frp', 10, 2)->nullable();
        $table->decimal('temperatura_brilho', 10, 2)->nullable();
        $table->integer('confianca')->nullable();
        $table->decimal('risco_fogo_inpe', 10, 2)->nullable();
        $table->timestamp('data_insercao')->nullable();
    });
});

function operationalUser(): User
{
    return new User([
        'id' => 1,
        'name' => 'Operador',
        'email' => 'operador@example.com',
        'municipio_id' => 1,
        'users_type_legacy' => 5,
    ]);
}

test('fullscreen fire map loads focos when filters are applied', function (): void {
    FocoSatelite::query()->create([
        'satelite_id' => 'NOAA-20',
        'latitude' => -15.1,
        'longitude' => -47.9,
        'data_hora_local' => '2026-04-05 10:00:00',
        'data_hora_gmt' => '2026-04-05 13:00:00',
    ]);

    Livewire::actingAs(operationalUser())
        ->test(FireFocosMap::class)
        ->set('fireMapFrom', '2026-04-01')
        ->set('fireMapTo', '2026-04-30')
        ->set('fireMapSatelite', 'NOAA-20')
        ->call('loadFireMap')
        ->assertCount('mapFocos', 1);
});

test('fullscreen fire map denies unauthenticated access', function (): void {
    Livewire::test(FireFocosMap::class)
        ->assertForbidden();
});

test('dashboard fullscreen url includes current filter query string', function (): void {
    $dashboard = new Dashboard;
    $dashboard->fireMapFrom = '2026-04-01';
    $dashboard->fireMapTo = '2026-04-30';
    $dashboard->fireMapSatelite = 'NOAA-20';

    expect($dashboard->fireMapFullscreenUrl())->toBe(route('dashboard.fire-map', [
        'from' => '2026-04-01',
        'to' => '2026-04-30',
        'satelite' => 'NOAA-20',
    ]));
});

test('dashboard fullscreen url omits empty satelite filter', function (): void {
    $dashboard = new Dashboard;
    $dashboard->fireMapFrom = '2026-04-01';
    $dashboard->fireMapTo = '2026-04-30';
    $dashboard->fireMapSatelite = '';

    expect($dashboard->fireMapFullscreenUrl())->toBe(route('dashboard.fire-map', [
        'from' => '2026-04-01',
        'to' => '2026-04-30',
    ]));
});
