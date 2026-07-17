<?php

declare(strict_types=1);

use App\Domain\Operations\Actions\CreateOperationalIncidentAction;
use App\Domain\Operations\DTOs\CreateIncidentDTO;
use App\Domain\Operations\Enums\CallType;
use App\Domain\Operations\Enums\IncidentStatus;
use App\Domain\Operations\Enums\UserLegacyProfile;
use App\Models\IncidentEvent;
use App\Models\Nature;
use App\Models\User;
use Database\Seeders\OperationalDemoSeeder;

beforeEach(function (): void {
    $this->seed(OperationalDemoSeeder::class);
});

function makeIncidentDTO(int $natureId, ?int $municipioId): CreateIncidentDTO
{
    return new CreateIncidentDTO(
        municipioId: $municipioId,
        natureId: $natureId,
        description: 'Ocorrência de teste',
        addressLine: 'Rua Teste',
        number: '100',
        district: 'Centro',
        city: 'Demo',
        callerName: 'Solicitante',
        callerPhone: null,
        patientAge: 40,
        patientSex: 'M',
        latitude: null,
        longitude: null,
        referenceNotes: null,
        callType: CallType::Normal,
        expectedVictimTotal: null,
        createdByUserId: null,
    );
}

test('natureza que exige regulação nasce aguardando regulação', function (): void {
    /** @var User $user */
    $user = User::query()->where('email', 'municipal@example.com')->firstOrFail();

    /** @var Nature $nature */
    $nature = Nature::query()->firstOrFail();
    $nature->update(['requires_medical_regulation' => true]);

    $incident = app(CreateOperationalIncidentAction::class)
        ->execute(makeIncidentDTO($nature->id, $user->municipio_id));

    expect($incident->status)->toBe(IncidentStatus::PendingRegulation);

    $queued = IncidentEvent::query()
        ->where('incident_id', $incident->id)
        ->where('event_key', 'regulation_queued')
        ->exists();

    expect($queued)->toBeTrue();
});

test('natureza sem regulação nasce aberta e vai direto ao despacho', function (): void {
    /** @var User $user */
    $user = User::query()->where('email', 'municipal@example.com')->firstOrFail();

    /** @var Nature $nature */
    $nature = Nature::query()->firstOrFail();
    $nature->update(['requires_medical_regulation' => false]);

    $incident = app(CreateOperationalIncidentAction::class)
        ->execute(makeIncidentDTO($nature->id, $user->municipio_id));

    expect($incident->status)->toBe(IncidentStatus::Open);

    $queued = IncidentEvent::query()
        ->where('incident_id', $incident->id)
        ->where('event_key', 'regulation_queued')
        ->exists();

    expect($queued)->toBeFalse();
});

test('perfil médico possui abilities de regulação', function (): void {
    expect(UserLegacyProfile::Doctor->abilities())
        ->toContain('regulation.view')
        ->toContain('regulation.regulate');

    /** @var User $medico */
    $medico = User::query()->where('email', 'medico@example.com')->firstOrFail();

    expect($medico->hasOperationalAbility('regulation.regulate'))->toBeTrue();
});

test('atendente e despachador não regulam', function (): void {
    /** @var User $atendente */
    $atendente = User::query()->where('email', 'atendente@example.com')->firstOrFail();
    /** @var User $despachador */
    $despachador = User::query()->where('email', 'despachador@example.com')->firstOrFail();

    expect($atendente->hasOperationalAbility('regulation.regulate'))->toBeFalse()
        ->and($despachador->hasOperationalAbility('regulation.regulate'))->toBeFalse();
});
