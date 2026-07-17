<?php

declare(strict_types=1);

use App\Models\User;
use Database\Seeders\OperationalDemoSeeder;

/** @var array<int, string> $operationsParameterRouteNames */
$operationsParameterRouteNames = [
    'operations.parameters.accessories',
    'operations.parameters.operational-supports',
    'operations.parameters.care-locals',
    'operations.parameters.injury-sites',
    'operations.parameters.natures',
    'operations.parameters.procedures',
    'operations.parameters.victim-types',
    'operations.parameters.health-units',
    'operations.parameters.checklist-resources',
];

beforeEach(function (): void {
    $this->seed(OperationalDemoSeeder::class);
});

test('operations pages redirect guests to login', function () use ($operationsParameterRouteNames): void {
    $this->get(route('operations.dispatch'))->assertRedirect();
    $this->get(route('operations.incidents.index'))->assertRedirect();
    $this->get(route('operations.fleet'))->assertRedirect();
    foreach ($operationsParameterRouteNames as $name) {
        $this->get(route($name))->assertRedirect();
    }
    $this->get(route('operations.cadastro.bases'))->assertRedirect();
    $this->get(route('operations.cadastro.vehicles'))->assertRedirect();
    $this->get(route('operations.cadastro.staff'))->assertRedirect();
    $this->get(route('operations.cadastro.shifts'))->assertRedirect();
    $this->get(route('operations.catalog.vehicles'))->assertRedirect();
    $this->get(route('operations.catalog.staff'))->assertRedirect();
    $this->get(route('operations.incidents.start'))->assertRedirect();
    $this->get(route('operations.admin.users'))->assertRedirect();
    $this->get(route('operations.incidents.create'))->assertForbidden();
});

test('municipal operator can open operational screens', function () use ($operationsParameterRouteNames): void {
    /** @var User $user */
    $user = User::query()->where('email', 'municipal@example.com')->firstOrFail();

    $this->actingAs($user);

    $this->get(route('operations.dispatch'))->assertOk();
    $this->get(route('operations.incidents.index'))->assertOk();
    $this->get(route('operations.fleet'))->assertOk();
    foreach ($operationsParameterRouteNames as $name) {
        $this->get(route($name))->assertForbidden();
    }
    $this->get(route('operations.cadastro.bases'))->assertForbidden();
    $this->get(route('operations.cadastro.vehicles'))->assertOk();
    $this->get(route('operations.cadastro.staff'))->assertOk();
    $this->get(route('operations.cadastro.shifts'))->assertOk();
    $this->get(route('operations.catalog.vehicles'))->assertRedirect(route('operations.cadastro.vehicles'));
    $this->get(route('operations.catalog.staff'))->assertRedirect(route('operations.cadastro.staff'));
    $this->get(route('operations.incidents.start'))->assertOk();
    $this->get(route('operations.incidents.create'))->assertOk();
    $this->get(route('operations.admin.users'))->assertForbidden();
});

test('central operator can open operational screens', function () use ($operationsParameterRouteNames): void {
    /** @var User $user */
    $user = User::query()->where('email', 'central@example.com')->firstOrFail();

    $this->actingAs($user);

    $this->get(route('operations.dispatch'))->assertOk();
    $this->get(route('operations.incidents.index'))->assertOk();
    $this->get(route('operations.incidents.start'))->assertOk();
    $this->get(route('operations.incidents.create'))->assertOk();
    foreach ($operationsParameterRouteNames as $name) {
        $this->get(route($name))->assertOk();
    }
    $this->get(route('operations.cadastro.bases'))->assertOk();
    $this->get(route('operations.cadastro.vehicles'))->assertOk();
    $this->get(route('operations.cadastro.staff'))->assertOk();
    $this->get(route('operations.cadastro.shifts'))->assertOk();
    $this->get(route('operations.catalog.vehicles'))->assertRedirect(route('operations.cadastro.vehicles'));
    $this->get(route('operations.catalog.staff'))->assertRedirect(route('operations.cadastro.staff'));
    $this->get(route('operations.fleet'))->assertOk();
    $this->get(route('operations.admin.users'))->assertOk();
});
