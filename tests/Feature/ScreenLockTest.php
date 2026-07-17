<?php

declare(strict_types=1);

use App\Models\User;
use App\Support\Auth\ScreenLock;

test('authenticated user can lock the screen', function (): void {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post(route('screen-lock.store'));

    $response->assertRedirect(route('screen-lock.show'));
    expect(session(ScreenLock::SESSION_KEY))->toBeTrue();
});

test('locked user is redirected to the secure area screen', function (): void {
    $user = User::factory()->create();

    $response = $this->actingAs($user)
        ->withSession([ScreenLock::SESSION_KEY => true])
        ->get(route('dashboard'));

    $response->assertRedirect(route('screen-lock.show'));
});

test('secure area screen can be rendered when locked', function (): void {
    $user = User::factory()->create();

    $response = $this->actingAs($user)
        ->withSession([ScreenLock::SESSION_KEY => true])
        ->get(route('screen-lock.show'));

    $response
        ->assertOk()
        ->assertSee(__('Área segura'), false)
        ->assertSee(__('Desbloquear'), false);
});

test('secure area screen redirects to dashboard when session is not locked', function (): void {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get(route('screen-lock.show'));

    $response->assertRedirect(route('dashboard'));
});

test('user can unlock the screen with the correct password', function (): void {
    $user = User::factory()->create();

    $response = $this->actingAs($user)
        ->withSession([
            ScreenLock::SESSION_KEY => true,
            ScreenLock::RETURN_URL_KEY => route('dashboard'),
        ])
        ->post(route('screen-lock.unlock'), [
            'password' => 'password',
        ]);

    $response
        ->assertRedirect(route('dashboard'))
        ->assertSessionHasNoErrors();

    expect(session(ScreenLock::SESSION_KEY))->toBeNull();
});

test('user cannot unlock the screen with an invalid password', function (): void {
    $user = User::factory()->create();

    $response = $this->actingAs($user)
        ->withSession([ScreenLock::SESSION_KEY => true])
        ->from(route('screen-lock.show'))
        ->post(route('screen-lock.unlock'), [
            'password' => 'wrong-password',
        ]);

    $response
        ->assertRedirect(route('screen-lock.show'))
        ->assertSessionHasErrors('password');

    expect(session(ScreenLock::SESSION_KEY))->toBeTrue();
});

test('locked user can still log out', function (): void {
    $user = User::factory()->create();

    $response = $this->actingAs($user)
        ->withSession([ScreenLock::SESSION_KEY => true])
        ->post(route('logout'));

    $response->assertRedirect(route('home'));
    $this->assertGuest();
});
