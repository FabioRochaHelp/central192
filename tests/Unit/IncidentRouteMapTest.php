<?php

declare(strict_types=1);

use App\Livewire\Operations\IncidentOperationalDetail;
use App\Livewire\Operations\IncidentRouteMap;
use App\Models\Incident;
use Livewire\Livewire;
use Tests\TestCase;

uses(TestCase::class);

test('incident route fullscreen map denies unauthenticated access', function (): void {
    Livewire::test(IncidentRouteMap::class, ['incident' => new Incident])
        ->assertForbidden();
});

test('incident detail fullscreen url points to route map page', function (): void {
    $incident = new Incident([
        'dispatch_year' => 2026,
        'talao' => 12345,
    ]);
    $incident->id = 42;

    $detail = new IncidentOperationalDetail;
    $detail->incident = $incident;

    expect($detail->routeMapFullscreenUrl())->toBe(
        route('operations.incidents.route-map', ['incident' => 42]),
    );
});
