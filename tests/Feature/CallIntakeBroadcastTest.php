<?php

declare(strict_types=1);

use App\Domain\Operations\Enums\UserLegacyProfile;
use App\Domain\Operations\Events\OperationalCallIntakeReceived;
use App\Models\User;
use App\Support\Auth\ScreenLock;
use Database\Seeders\OperationalDemoSeeder;
use Illuminate\Broadcasting\PrivateChannel;
use Livewire\Livewire;

beforeEach(function (): void {
    $this->seed(OperationalDemoSeeder::class);
});

test('incident intake event broadcasts on operations call intake channel', function (): void {
    $event = new OperationalCallIntakeReceived(
        formUrl: 'https://example.test/form',
        phoneDigits: '11987654321',
        expiresAtIso: now()->addMinutes(30)->toIso8601String(),
    );

    expect($event->broadcastOn())->toHaveCount(1)
        ->and($event->broadcastOn()[0])->toBeInstanceOf(PrivateChannel::class)
        ->and($event->broadcastOn()[0]->name)->toBe('private-operations.call-intake');
});

test('attendant is always authorized on call intake channel', function (): void {
    /** @var User $attendant */
    $attendant = User::query()->where('email', 'atendente@example.com')->firstOrFail();

    $this->actingAs($attendant)
        ->postJson('/broadcasting/auth', [
            'socket_id' => '1.1',
            'channel_name' => 'private-operations.call-intake',
        ])
        ->assertOk();
});

test('dispatcher is authorized on call intake channel only when enabled', function (): void {
    /** @var User $dispatcher */
    $dispatcher = User::query()->where('email', 'despachador@example.com')->firstOrFail();

    $dispatcher->update(['call_intake_websocket_enabled' => false]);

    $this->actingAs($dispatcher)
        ->postJson('/broadcasting/auth', [
            'socket_id' => '1.1',
            'channel_name' => 'private-operations.call-intake',
        ])
        ->assertForbidden();

    $dispatcher->update(['call_intake_websocket_enabled' => true]);

    $this->actingAs($dispatcher)
        ->postJson('/broadcasting/auth', [
            'socket_id' => '1.1',
            'channel_name' => 'private-operations.call-intake',
        ])
        ->assertOk();
});

test('municipal operator is not authorized on call intake channel', function (): void {
    /** @var User $user */
    $user = User::query()->where('email', 'municipal@example.com')->firstOrFail();

    $this->actingAs($user)
        ->postJson('/broadcasting/auth', [
            'socket_id' => '1.1',
            'channel_name' => 'private-operations.call-intake',
        ])
        ->assertForbidden();
});

test('dispatcher can toggle call intake websocket preference', function (): void {
    /** @var User $dispatcher */
    $dispatcher = User::query()->where('email', 'despachador@example.com')->firstOrFail();

    Livewire::actingAs($dispatcher)
        ->test('dispatcher-call-intake-toggle')
        ->set('enabled', true)
        ->assertSet('enabled', true);

    expect($dispatcher->fresh()->call_intake_websocket_enabled)->toBeTrue()
        ->and($dispatcher->fresh()->receivesOperationalCallIntakeBroadcast())->toBeTrue();
});

test('attendant always receives call intake broadcast flag', function (): void {
    /** @var User $attendant */
    $attendant = User::query()->where('email', 'atendente@example.com')->firstOrFail();

    expect($attendant->legacyProfile())->toBe(UserLegacyProfile::Attendant)
        ->and($attendant->receivesOperationalCallIntakeBroadcast())->toBeTrue()
        ->and($attendant->shouldMountOperationalCallIntakeBridge())->toBeTrue();
});

test('dispatcher mounts call intake bridge even when websocket preference is disabled', function (): void {
    /** @var User $dispatcher */
    $dispatcher = User::query()->where('email', 'despachador@example.com')->firstOrFail();

    $dispatcher->update(['call_intake_websocket_enabled' => false]);

    expect($dispatcher->fresh()->receivesOperationalCallIntakeBroadcast())->toBeFalse()
        ->and($dispatcher->fresh()->shouldMountOperationalCallIntakeBridge())->toBeTrue();
});

test('broadcasting auth is allowed while screen is locked', function (): void {
    /** @var User $attendant */
    $attendant = User::query()->where('email', 'atendente@example.com')->firstOrFail();

    $this->actingAs($attendant)
        ->withSession([ScreenLock::SESSION_KEY => true])
        ->postJson('/broadcasting/auth', [
            'socket_id' => '1.1',
            'channel_name' => 'private-operations.call-intake',
        ])
        ->assertOk();
});
