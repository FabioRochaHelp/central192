<?php

declare(strict_types=1);

use App\Models\User;
use App\Models\UserType;
use Database\Seeders\OperationalDemoSeeder;

beforeEach(function (): void {
    $this->seed(OperationalDemoSeeder::class);
});

test('guest is redirected from system users page', function (): void {
    $this->get(route('operations.admin.users'))->assertRedirect();
});

test('municipal operator cannot manage system users', function (): void {
    /** @var User $user */
    $user = User::query()->where('email', 'municipal@example.com')->firstOrFail();

    $this->actingAs($user)->get(route('operations.admin.users'))->assertForbidden();
});

test('central operator without admin legacy cannot manage system users', function (): void {
    $type = UserType::query()->firstOrFail();

    /** @var User $user */
    $user = User::factory()->create([
        'email' => 'central.operator@example.test',
        'user_type_id' => $type->id,
        'users_type_legacy' => 2,
        'municipio_id' => null,
    ]);

    $this->actingAs($user)->get(route('operations.admin.users'))->assertForbidden();
});

test('central administrator can open system users page', function (): void {
    /** @var User $user */
    $user = User::query()->where('email', 'central@example.com')->firstOrFail();

    $this->actingAs($user)->get(route('operations.admin.users'))->assertOk();
});
