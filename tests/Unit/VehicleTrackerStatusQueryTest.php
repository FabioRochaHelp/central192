<?php

declare(strict_types=1);

use App\Integrations\Traccar\DTOs\TraccarDevice;
use App\Models\Vehicle;
use App\Support\Operations\VehicleTrackerStatusQuery;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

uses(TestCase::class);

beforeEach(function (): void {
    Cache::flush();

    config([
        'traccar.base_url' => 'https://traccar.test',
        'traccar.auth_type' => 'basic',
        'traccar.username' => 'reader',
        'traccar.password' => 'secret',
        'traccar.positions_sync_interval' => 60,
        'traccar.device_online_threshold_seconds' => 180,
    ]);
});

test('vehicle tracker status resolves online and offline from traccar devices', function (): void {
    Http::fake([
        'https://traccar.test/api/devices*' => Http::response([
            [
                'id' => 1001,
                'name' => 'US 01',
                'uniqueId' => 'imei-1',
                'status' => 'online',
                'lastUpdate' => now()->subMinute()->toIso8601String(),
            ],
            [
                'id' => 1002,
                'name' => 'US 02',
                'uniqueId' => 'imei-2',
                'status' => 'offline',
                'lastUpdate' => now()->subHour()->toIso8601String(),
            ],
        ], 200),
    ]);

    $onlineVehicle = new Vehicle(['device_id' => '1001']);
    $onlineVehicle->id = 1;

    $offlineVehicle = new Vehicle(['device_id' => '1002']);
    $offlineVehicle->id = 2;

    $noDeviceVehicle = new Vehicle(['device_id' => null]);
    $noDeviceVehicle->id = 3;

    $statuses = VehicleTrackerStatusQuery::forVehicles(collect([$onlineVehicle, $offlineVehicle, $noDeviceVehicle]));

    expect($statuses)->toBe([
        1 => VehicleTrackerStatusQuery::Online,
        2 => VehicleTrackerStatusQuery::Offline,
        3 => VehicleTrackerStatusQuery::NoDevice,
    ]);
});

test('device online in traccar stays online even when last fix is stale (parked vehicle)', function (): void {
    Http::fake([
        'https://traccar.test/api/devices*' => Http::response([
            [
                'id' => 1001,
                'name' => 'US 01',
                'uniqueId' => 'imei-1',
                'status' => 'online',
                'lastUpdate' => now()->subHours(2)->toIso8601String(),
            ],
        ], 200),
    ]);

    $vehicle = new Vehicle(['device_id' => '1001']);
    $vehicle->id = 1;

    $statuses = VehicleTrackerStatusQuery::forVehicles(collect([$vehicle]));

    expect($statuses[1])->toBe(VehicleTrackerStatusQuery::Online);
});

test('device offline in traccar is offline even with a recent last update', function (): void {
    Http::fake([
        'https://traccar.test/api/devices*' => Http::response([
            [
                'id' => 1001,
                'name' => 'US 01',
                'uniqueId' => 'imei-1',
                'status' => 'offline',
                'lastUpdate' => now()->subSeconds(30)->toIso8601String(),
            ],
        ], 200),
    ]);

    $vehicle = new Vehicle(['device_id' => '1001']);
    $vehicle->id = 1;

    $statuses = VehicleTrackerStatusQuery::forVehicles(collect([$vehicle]));

    expect($statuses[1])->toBe(VehicleTrackerStatusQuery::Offline);
});

test('device with unknown status falls back to last update recency', function (): void {
    Http::fake([
        'https://traccar.test/api/devices*' => Http::response([
            [
                'id' => 1001,
                'name' => 'US 01',
                'uniqueId' => 'imei-1',
                'status' => 'unknown',
                'lastUpdate' => now()->subMinute()->toIso8601String(),
            ],
        ], 200),
    ]);

    $vehicle = new Vehicle(['device_id' => '1001']);
    $vehicle->id = 1;

    $statuses = VehicleTrackerStatusQuery::forVehicles(collect([$vehicle]));

    expect($statuses[1])->toBe(VehicleTrackerStatusQuery::Online);
});

test('unknown device id returns unknown status when device is missing from traccar', function (): void {
    Http::fake([
        'https://traccar.test/api/devices*' => Http::response([], 200),
    ]);

    $vehicle = new Vehicle(['device_id' => '9999']);
    $vehicle->id = 5;

    $statuses = VehicleTrackerStatusQuery::forVehicles(collect([$vehicle]));

    expect($statuses[5])->toBe(VehicleTrackerStatusQuery::Unknown);
});

test('traccar device is reporting only with recent last update', function (): void {
    $reporting = TraccarDevice::fromArray([
        'id' => 1,
        'name' => 'VTR',
        'uniqueId' => 'abc',
        'status' => 'online',
        'lastUpdate' => now()->subMinute()->toIso8601String(),
    ]);

    $stale = TraccarDevice::fromArray([
        'id' => 2,
        'name' => 'VTR',
        'uniqueId' => 'def',
        'status' => 'online',
        'lastUpdate' => now()->subHours(1)->toIso8601String(),
    ]);

    expect($reporting->isReportingAt())->toBeTrue()
        ->and($stale->isReportingAt())->toBeFalse();
});

test('vehicle tracker status labels are human readable', function (): void {
    expect(VehicleTrackerStatusQuery::label(VehicleTrackerStatusQuery::Online))
        ->toBe(__('Rastreador online'))
        ->and(VehicleTrackerStatusQuery::label(VehicleTrackerStatusQuery::NoDevice))
        ->toBe(__('Sem rastreador vinculado'));
});
