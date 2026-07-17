<?php

declare(strict_types=1);

use App\Domain\Operations\Enums\IncidentStatus;
use App\Domain\Operations\Enums\PrescriptionStatus;
use App\Livewire\Operations\VictimRecord;
use App\Livewire\Operations\Victims\PrescriptionApproval;
use App\Livewire\Operations\Victims\PrescriptionForm;
use App\Models\CareLocal;
use App\Models\Incident;
use App\Models\InjurySite;
use App\Models\Municipio;
use App\Models\Nature;
use App\Models\Prescription;
use App\Models\Procedure;
use App\Domain\Operations\Enums\StaffCargo;
use App\Models\Staff;
use App\Models\User;
use App\Models\Victim;
use Database\Seeders\OperationalDemoSeeder;
use Livewire\Livewire;

beforeEach(function (): void {
    $this->seed(OperationalDemoSeeder::class);
});

function victimRecordTestIncident(): Incident
{
    /** @var Municipio $municipio */
    $municipio = Municipio::query()->firstOrFail();
    $nature = Nature::query()->firstOrFail();

    return Incident::query()->create([
        'municipio_id' => $municipio->id,
        'dispatch_year' => (int) now()->format('Y'),
        'talao' => 790000 + random_int(1, 999),
        'status' => IncidentStatus::Open,
        'nature_id' => $nature->id,
        'occurred_at' => now(),
        'patient_call_type' => 'N',
        'description' => 'Ocorrência para teste de vítima',
        'caller_phone' => '11999996666',
    ]);
}

test('guest is redirected from victim registration route', function (): void {
    $incident = victimRecordTestIncident();

    $this->get(route('operations.incidents.victims.create', $incident))
        ->assertRedirect();
});

test('nurse profile cannot open victim registration screen', function (): void {
    $incident = victimRecordTestIncident();

    /** @var User $nurse */
    $nurse = User::query()->where('email', 'enfermeiro@example.com')->firstOrFail();

    $this->actingAs($nurse)
        ->get(route('operations.incidents.victims.create', $incident))
        ->assertForbidden();
});

test('nurse profile cannot edit victim record', function (): void {
    $incident = victimRecordTestIncident();
    /** @var Municipio $municipio */
    $municipio = Municipio::query()->firstOrFail();

    $victim = Victim::query()->create([
        'municipio_id' => $municipio->id,
        'incident_id' => $incident->id,
        'name' => 'Paciente teste',
        'situacao' => 1,
    ]);

    /** @var User $nurse */
    $nurse = User::query()->where('email', 'enfermeiro@example.com')->firstOrFail();

    $this->actingAs($nurse)
        ->get(route('operations.incidents.victims.edit', [$incident, $victim]))
        ->assertForbidden();
});

test('municipal operator can open victim registration screen', function (): void {
    $incident = victimRecordTestIncident();

    /** @var User $municipal */
    $municipal = User::query()->where('email', 'municipal@example.com')->firstOrFail();

    $this->actingAs($municipal)
        ->get(route('operations.incidents.victims.create', $incident))
        ->assertOk();
});

test('municipal operator can save victim with procedures and timeline records event', function (): void {
    $incident = victimRecordTestIncident();
    $procedure = Procedure::query()->firstOrFail();
    $injurySite = InjurySite::query()->where('name', 'Tórax - Corte')->firstOrFail();

    /** @var User $municipal */
    $municipal = User::query()->where('email', 'municipal@example.com')->firstOrFail();

    Livewire::actingAs($municipal)
        ->test(VictimRecord::class, ['incident' => $incident])
        ->set('name', 'Maria Silva')
        ->set('sex', '2')
        ->set('situacao', '1')
        ->set('procedure_ids', [(string) $procedure->id])
        ->set('injury_site_ids', [(string) $injurySite->id])
        ->call('save')
        ->assertHasNoErrors()
        ->assertRedirect(route('operations.incidents.show', $incident));

    $victim = Victim::query()->where('incident_id', $incident->id)->first();
    expect($victim)->not->toBeNull()
        ->and($victim->name)->toBe('Maria Silva')
        ->and($victim->procedures()->pluck('procedures.id')->all())->toContain($procedure->id)
        ->and($victim->injurySites()->pluck('injury_sites.id')->all())->toContain($injurySite->id);

    $matrixCell = $victim->injuryMatrixEntries()->first();
    expect($matrixCell)->not->toBeNull()
        ->and($matrixCell->matrix_region)->toBe('Tórax')
        ->and($matrixCell->matrix_lesion)->toBe('Corte')
        ->and($matrixCell->injury_site_id)->toBe($injurySite->id);

    expect($incident->fresh()->incidentEvents()->where('event_key', 'victim_recorded')->exists())->toBeTrue();
});

test('municipal operator can select body region and toggle injury from human diagram', function (): void {
    $incident = victimRecordTestIncident();
    $injurySite = InjurySite::query()->where('name', 'Tórax - Corte')->firstOrFail();

    /** @var User $municipal */
    $municipal = User::query()->where('email', 'municipal@example.com')->firstOrFail();

    Livewire::actingAs($municipal)
        ->test(VictimRecord::class, ['incident' => $incident])
        ->set('situacao', '1')
        ->call('selectInjuryRegion', 'Tórax')
        ->assertSet('selected_injury_region', 'Tórax')
        ->call('toggleInjurySite', $injurySite->id)
        ->assertSet('injury_site_ids', [$injurySite->id])
        ->assertSee('Ferimentos informados')
        ->call('toggleInjurySite', $injurySite->id)
        ->assertSet('injury_site_ids', []);
});

test('victim registration shows clickable human injury selector', function (): void {
    $incident = victimRecordTestIncident();

    /** @var User $municipal */
    $municipal = User::query()->where('email', 'municipal@example.com')->firstOrFail();

    Livewire::actingAs($municipal)
        ->test(VictimRecord::class, ['incident' => $incident])
        ->set('situacao', '1')
        ->assertSee('Clique na região do corpo')
        ->assertSee('Frente')
        ->assertSee('Dorso')
        ->assertSee('Região selecionada');
});

test('injury sites from parameters without standard matrix lesion appear in sidebar for region', function (): void {
    $incident = victimRecordTestIncident();

    InjurySite::query()->create([
        'name' => 'Tórax — avaliação clínica customizada',
    ]);

    /** @var User $municipal */
    $municipal = User::query()->where('email', 'municipal@example.com')->firstOrFail();

    Livewire::actingAs($municipal)
        ->test(VictimRecord::class, ['incident' => $incident])
        ->set('situacao', '1')
        ->call('selectInjuryRegion', 'Tórax')
        ->assertSee('Outros cadastrados nesta região')
        ->assertSee('Tórax — avaliação clínica customizada');
});

test('victim situation buttons reveal the corresponding form sections', function (): void {
    $incident = victimRecordTestIncident();

    /** @var User $municipal */
    $municipal = User::query()->where('email', 'municipal@example.com')->firstOrFail();

    Livewire::actingAs($municipal)
        ->test(VictimRecord::class, ['incident' => $incident])
        ->assertSee('Atendida')
        ->assertSee('Recusa de atendimento')
        ->assertSee('Óbito')
        ->set('situacao', '3')
        ->assertSee('Testemunha')
        ->set('situacao', '2')
        ->assertSee('Óbito — onde')
        ->set('situacao', '1')
        ->assertSee('Sinais vitais seriados');
});

test('death form can save scene location as death place', function (): void {
    $incident = victimRecordTestIncident();
    $careLocal = CareLocal::query()->firstOrFail();

    /** @var User $municipal */
    $municipal = User::query()->where('email', 'municipal@example.com')->firstOrFail();

    Livewire::actingAs($municipal)
        ->test(VictimRecord::class, ['incident' => $incident])
        ->set('name', 'Paciente óbito')
        ->set('sex', '1')
        ->set('situacao', '2')
        ->set('care_local_id', (string) $careLocal->id)
        ->set('death_notes', 'Óbito constatado no local.')
        ->call('save')
        ->assertHasNoErrors();

    $victim = Victim::query()->where('incident_id', $incident->id)->firstOrFail();

    expect($victim->situacao)->toBe(2)
        ->and($victim->care_local_id)->toBe($careLocal->id)
        ->and($victim->death_notes)->toBe('Óbito constatado no local.');
});

test('victim edit route returns 404 when victim belongs to another incident', function (): void {
    $incidentA = victimRecordTestIncident();
    $incidentB = victimRecordTestIncident();
    /** @var Municipio $municipio */
    $municipio = Municipio::query()->firstOrFail();

    $victimOnA = Victim::query()->create([
        'municipio_id' => $municipio->id,
        'incident_id' => $incidentA->id,
        'name' => 'Só na A',
        'situacao' => 1,
    ]);

    /** @var User $municipal */
    $municipal = User::query()->where('email', 'municipal@example.com')->firstOrFail();

    $this->actingAs($municipal)
        ->get(route('operations.incidents.victims.edit', [$incidentB, $victimOnA]))
        ->assertNotFound();
});

test('medical user can create prescription for attended victim without stock', function (): void {
    $incident = victimRecordTestIncident();
    /** @var Municipio $municipio */
    $municipio = Municipio::query()->firstOrFail();

    $victim = Victim::query()->create([
        'municipio_id' => $municipio->id,
        'incident_id' => $incident->id,
        'name' => 'Paciente atendido',
        'sex' => 1,
        'situacao' => 1,
    ]);

    /** @var User $doctor */
    $doctor = User::query()->where('email', 'medico@example.com')->firstOrFail();
    /** @var Staff $staff */
    $staff = Staff::query()->where('cargo', StaffCargo::Socorrista->value)->firstOrFail();

    Livewire::actingAs($doctor)
        ->test(PrescriptionForm::class, ['victim' => $victim])
        ->set('medical_staff_id', (string) $staff->id)
        ->set('description', 'Administrar se dor intensa.')
        ->set('items.0.medication_name', 'Dipirona')
        ->set('items.0.quantity', '2')
        ->call('save')
        ->assertHasNoErrors();

    $prescription = Prescription::query()->where('victim_id', $victim->id)->firstOrFail();

    expect($prescription->status)->toBe(PrescriptionStatus::Pending)
        ->and($prescription->items()->first()?->medication_name)->toBe('Dipirona')
        ->and($incident->fresh()->incidentEvents()->where('event_key', 'prescription_created')->exists())->toBeTrue();
});

test('medical user cannot create prescription for refusal victim', function (): void {
    $incident = victimRecordTestIncident();
    /** @var Municipio $municipio */
    $municipio = Municipio::query()->firstOrFail();

    $victim = Victim::query()->create([
        'municipio_id' => $municipio->id,
        'incident_id' => $incident->id,
        'name' => 'Paciente recusou',
        'sex' => 2,
        'situacao' => 3,
    ]);

    /** @var User $doctor */
    $doctor = User::query()->where('email', 'medico@example.com')->firstOrFail();

    Livewire::actingAs($doctor)
        ->test(PrescriptionForm::class, ['victim' => $victim])
        ->assertForbidden();
});

test('medical user can approve pending prescription without stock decrement', function (): void {
    $incident = victimRecordTestIncident();
    /** @var Municipio $municipio */
    $municipio = Municipio::query()->firstOrFail();
    /** @var User $doctor */
    $doctor = User::query()->where('email', 'medico@example.com')->firstOrFail();

    $victim = Victim::query()->create([
        'municipio_id' => $municipio->id,
        'incident_id' => $incident->id,
        'name' => 'Paciente com prescrição',
        'sex' => 1,
        'situacao' => 1,
    ]);

    $prescription = Prescription::query()->create([
        'victim_id' => $victim->id,
        'prescribed_by_user_id' => $doctor->id,
        'status' => PrescriptionStatus::Pending,
        'description' => null,
    ]);
    $prescription->items()->create([
        'medication_name' => 'Soro fisiológico',
        'quantity' => 1,
    ]);

    Livewire::actingAs($doctor)
        ->test(PrescriptionApproval::class, ['prescription' => $prescription])
        ->call('approve')
        ->assertHasNoErrors();

    $prescription->refresh();

    expect($prescription->status)->toBe(PrescriptionStatus::Approved)
        ->and($prescription->approved_by_user_id)->toBe($doctor->id)
        ->and($incident->fresh()->incidentEvents()->where('event_key', 'prescription_approved')->exists())->toBeTrue();
});
