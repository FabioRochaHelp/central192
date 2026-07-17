<?php

declare(strict_types=1);

use App\Models\User;
use Database\Seeders\OperationalDemoSeeder;

beforeEach(function (): void {
    $this->seed(OperationalDemoSeeder::class);
});

test('dispatcher can access cco fleet and incidents but not cadastro staff', function (): void {
    /** @var User $user */
    $user = User::query()->where('email', 'despachador@example.com')->firstOrFail();

    $this->actingAs($user)
        ->get(route('operations.dispatch'))
        ->assertOk();

    $this->actingAs($user)
        ->get(route('operations.fleet'))
        ->assertOk();

    $this->actingAs($user)
        ->get(route('operations.incidents.index'))
        ->assertOk();

    $this->actingAs($user)
        ->get(route('operations.incidents.start'))
        ->assertOk();

    $this->actingAs($user)
        ->get(route('operations.cadastro.staff'))
        ->assertForbidden();
});

test('attendant can access incidents and new incident but not cco or fleet', function (): void {
    /** @var User $user */
    $user = User::query()->where('email', 'atendente@example.com')->firstOrFail();

    $this->actingAs($user)
        ->get(route('operations.incidents.index'))
        ->assertOk();

    $this->actingAs($user)
        ->get(route('operations.incidents.start'))
        ->assertOk();

    $this->actingAs($user)
        ->get(route('operations.dispatch'))
        ->assertForbidden();

    $this->actingAs($user)
        ->get(route('operations.fleet'))
        ->assertForbidden();
});

test('dispatcher sidebar shows cco and fleet but attendant does not', function (): void {
    /** @var User $dispatcher */
    $dispatcher = User::query()->where('email', 'despachador@example.com')->firstOrFail();

    $this->actingAs($dispatcher)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee(__('CCO'))
        ->assertSee(__('Turnos e viaturas'))
        ->assertSee(__('Nova ocorrência'));

    /** @var User $attendant */
    $attendant = User::query()->where('email', 'atendente@example.com')->firstOrFail();

    $response = $this->actingAs($attendant)->get(route('dashboard'));

    $response
        ->assertOk()
        ->assertSee(__('Ocorrências'))
        ->assertSee(__('Nova ocorrência'))
        ->assertDontSee(__('CCO'))
        ->assertDontSee(__('Turnos e viaturas'));
});
