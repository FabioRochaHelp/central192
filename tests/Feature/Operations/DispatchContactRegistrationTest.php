<?php

declare(strict_types=1);

use App\Livewire\Operations\DispatchBoard;
use App\Models\Incident;
use App\Models\Nature;
use App\Models\User;
use Database\Seeders\OperationalDemoSeeder;
use Livewire\Livewire;

beforeEach(function (): void {
    $this->seed(OperationalDemoSeeder::class);
});

test('dispatch board registers contact before dispatching unit', function (): void {
    $user = User::query()->where('email', 'municipal@example.com')->firstOrFail();
    $nature = Nature::query()->firstOrFail();

    $incident = Incident::query()->create([
        'municipio_id' => $user->municipio_id,
        'dispatch_year' => (int) now()->format('Y'),
        'talao' => 1,
        'status' => 'open',
        'nature_id' => $nature->id,
        'occurred_at' => now(),
        'call_received_at' => now(),
        'caller_name' => 'Teste',
        'caller_phone' => '99999-0000',
        'patient_call_type' => 'N',
        'created_by' => $user->id,
    ]);

    Livewire::actingAs($user)
        ->test(DispatchBoard::class)
        ->call('openDispatchModal', $incident->id)
        ->set('dispatchContactMethod', 'telefone')
        ->set('dispatchContactDetails', '99999-0000')
        ->set('dispatchContactSuccessful', true)
        ->call('confirmDispatch')
        ->assertHasNoErrors()
        ->assertSet('boardMessage', __('Equipe empenhada.'));

    $fresh = $incident->fresh();

    expect($fresh->incidentEvents()->where('event_key', 'dispatch_contact_attempted')->exists())->toBeTrue();
    expect($fresh->incidentEvents()->where('event_key', 'unit_dispatched')->exists())->toBeTrue();
});

test('dispatch board records failed contact and does not dispatch unit', function (): void {
    $user = User::query()->where('email', 'municipal@example.com')->firstOrFail();
    $nature = Nature::query()->firstOrFail();

    $incident = Incident::query()->create([
        'municipio_id' => $user->municipio_id,
        'dispatch_year' => (int) now()->format('Y'),
        'talao' => 2,
        'status' => 'open',
        'nature_id' => $nature->id,
        'occurred_at' => now(),
        'call_received_at' => now(),
        'caller_name' => 'Teste',
        'caller_phone' => '99999-0001',
        'patient_call_type' => 'N',
        'created_by' => $user->id,
    ]);

    Livewire::actingAs($user)
        ->test(DispatchBoard::class)
        ->call('openDispatchModal', $incident->id)
        ->set('dispatchContactMethod', 'telefone')
        ->set('dispatchContactDetails', '99999-0001')
        ->set('dispatchContactSuccessful', false)
        ->set('dispatchContactReason', 'Não atende')
        ->call('confirmDispatch')
        ->assertHasNoErrors()
        ->assertSet('boardMessage', __('Contato registrado. Viatura não empenhada e responsável pela viatura mais próxima foi acionado.'));

    $fresh = $incident->fresh();

    expect($fresh->incidentEvents()->where('event_key', 'dispatch_contact_failed')->exists())->toBeTrue();
    expect($fresh->incidentEvents()->where('event_key', 'unit_dispatched')->exists())->toBeFalse();
});
