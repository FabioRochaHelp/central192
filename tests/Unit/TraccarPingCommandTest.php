<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

uses(TestCase::class);

beforeEach(function (): void {
    config([
        'traccar.base_url' => 'https://traccar.test',
        'traccar.auth_type' => 'basic',
        'traccar.username' => 'reader',
        'traccar.password' => 'secret',
        'traccar.device_online_threshold_seconds' => 180,
    ]);
});

test('traccar ping devices lists computed connectivity from last update', function (): void {
    Http::fake([
        'https://traccar.test/api/server' => Http::response(['id' => 1], 200),
        'https://traccar.test/api/devices*' => Http::response([
            [
                'id' => 10,
                'name' => 'US 01',
                'uniqueId' => 'imei-1',
                'status' => 'online',
                'lastUpdate' => now()->subHours(2)->toIso8601String(),
            ],
        ], 200),
    ]);

    Artisan::call('traccar:ping', ['--devices' => true]);

    $output = Artisan::output();

    expect($output)
        ->toContain('online')
        ->toContain('offline')
        ->toContain('Status API');
});
