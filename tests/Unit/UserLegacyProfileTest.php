<?php

declare(strict_types=1);

use App\Domain\Operations\Enums\UserLegacyProfile;
use App\Models\User;
use Tests\TestCase;

uses(TestCase::class);

test('dispatcher profile exposes dispatch and incident creation abilities', function (): void {
    $abilities = UserLegacyProfile::Dispatcher->abilities();

    expect($abilities)->toContain('dispatch.view')
        ->and($abilities)->toContain('incident.create')
        ->and($abilities)->toContain('dispatch.assign_unit')
        ->and($abilities)->not->toContain('catalog.manage')
        ->and($abilities)->not->toContain('incident.view');
});

test('attendant profile exposes only incident view and creation abilities', function (): void {
    $abilities = UserLegacyProfile::Attendant->abilities();

    expect($abilities)->toBe([
        'incident.view',
        'incident.create',
    ]);
});

test('dispatcher and attendant profiles have multi municipio access', function (): void {
    expect(UserLegacyProfile::Dispatcher->hasMultiMunicipioAccess())->toBeTrue()
        ->and(UserLegacyProfile::Attendant->hasMultiMunicipioAccess())->toBeTrue()
        ->and(UserLegacyProfile::Dispatcher->requiresMunicipio())->toBeFalse()
        ->and(UserLegacyProfile::Attendant->requiresMunicipio())->toBeFalse()
        ->and(UserLegacyProfile::MunicipalOperator->requiresMunicipio())->toBeTrue();
});

test('médico regulador atua sobre todos os municípios', function (): void {
    expect(UserLegacyProfile::Doctor->hasMultiMunicipioAccess())->toBeTrue()
        ->and(UserLegacyProfile::Doctor->requiresMunicipio())->toBeFalse();
});

test('multi municipio users can access any municipio', function (): void {
    $dispatcher = User::factory()->make([
        'users_type_legacy' => UserLegacyProfile::Dispatcher->value,
        'municipio_id' => null,
    ]);

    $attendant = User::factory()->make([
        'users_type_legacy' => UserLegacyProfile::Attendant->value,
        'municipio_id' => null,
    ]);

    expect($dispatcher->hasMultiMunicipioAccess())->toBeTrue()
        ->and($dispatcher->canAccessOperationalMunicipio(42))->toBeTrue()
        ->and($attendant->canAccessOperationalMunicipio(99))->toBeTrue();
});
