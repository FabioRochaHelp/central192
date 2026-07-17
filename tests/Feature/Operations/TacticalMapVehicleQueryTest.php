<?php

declare(strict_types=1);

use App\Domain\Operations\Enums\ShiftStatus;
use App\Models\Shift;
use App\Models\Vehicle;
use App\Models\VehiclePosition;
use App\Support\Operations\TacticalMapVehicleQuery;
use Database\Seeders\OperationalDemoSeeder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

beforeEach(function (): void {
    $this->seed(OperationalDemoSeeder::class);
});

test('tactical map vehicles query includes active shifts and marks dispatch status', function (): void {
    /** @var Vehicle $vehicle */
    $vehicle = Vehicle::query()->firstOrFail();
    /** @var Shift $shift */
    $shift = Shift::query()->where('vehicle_id', $vehicle->id)->firstOrFail();

    VehiclePosition::query()->create([
        'vehicle_id' => $vehicle->id,
        'device_id' => '1001',
        'latitude' => -22.0,
        'longitude' => -50.0,
        'speed_kmh' => 10,
        'valid' => true,
        'fix_time' => now(),
        'synced_at' => now(),
    ]);

    expect(TacticalMapVehicleQuery::vehicleIsOnMap($vehicle->id))->toBeTrue()
        ->and(TacticalMapVehicleQuery::vehicleIsOnDispatch($vehicle->id))->toBeFalse();

    $available = TacticalMapVehicleQuery::mapPayload()->firstWhere('vehicle_id', $vehicle->id);
    expect($available)->not->toBeNull()
        ->and($available['on_dispatch'])->toBeFalse();

    $shift->update(['status' => ShiftStatus::Empenhado]);

    expect(TacticalMapVehicleQuery::vehicleIsOnDispatch($vehicle->id))->toBeTrue();

    $dispatched = TacticalMapVehicleQuery::mapPayload()->firstWhere('vehicle_id', $vehicle->id);
    expect($dispatched)->not->toBeNull()
        ->and($dispatched['on_dispatch'])->toBeTrue();

    $shift->update(['status' => ShiftStatus::Baixado]);

    expect(TacticalMapVehicleQuery::vehicleIsOnMap($vehicle->id))->toBeFalse()
        ->and(TacticalMapVehicleQuery::mapPayload()->pluck('vehicle_id'))->not->toContain($vehicle->id);
});

test('tactical map uses live traccar position when shift exists without stored position', function (): void {
    Cache::flush();

    config([
        'traccar.base_url' => 'https://traccar.test',
        'traccar.auth_type' => 'basic',
        'traccar.username' => 'reader',
        'traccar.password' => 'secret',
        'traccar.positions_sync_interval' => 60,
    ]);

    Http::fake([
        'https://traccar.test/api/positions*' => Http::response([
            [
                'id' => 1,
                'deviceId' => 1001,
                'fixTime' => now()->toIso8601String(),
                'latitude' => -22.5,
                'longitude' => -50.5,
                'altitude' => 0,
                'speed' => 12,
                'course' => 90,
                'address' => 'Via pública',
                'valid' => true,
            ],
        ], 200),
    ]);

    /** @var Vehicle $vehicle */
    $vehicle = Vehicle::query()->firstOrFail();
    $vehicle->update(['device_id' => '1001']);
    VehiclePosition::query()->where('vehicle_id', $vehicle->id)->delete();

    $payload = TacticalMapVehicleQuery::mapPayload()->firstWhere('vehicle_id', $vehicle->id);

    expect($payload)->not->toBeNull()
        ->and($payload['lat'])->toBe(-22.5)
        ->and($payload['lng'])->toBe(-50.5);

    expect(VehiclePosition::query()->where('vehicle_id', $vehicle->id)->exists())->toBeTrue();
});
