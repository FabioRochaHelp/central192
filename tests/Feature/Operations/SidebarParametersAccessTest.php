<?php

declare(strict_types=1);

use App\Models\User;
use Database\Seeders\OperationalDemoSeeder;

beforeEach(function (): void {
    $this->seed(OperationalDemoSeeder::class);
});

test('administrador central vê Parâmetros e Relatórios no menu', function (): void {
    /** @var User $admin */
    $admin = User::query()->where('email', 'central@example.com')->firstOrFail();

    $this->actingAs($admin)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee(__('Parâmetros'))
        ->assertSee(__('Relatórios'));
});

test('médico regulador vê Relatórios mas não Parâmetros', function (): void {
    /** @var User $medico */
    $medico = User::query()->where('email', 'medico@example.com')->firstOrFail();

    $this->actingAs($medico)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee(__('Regulação'))
        ->assertDontSee(__('Parâmetros'));
});
