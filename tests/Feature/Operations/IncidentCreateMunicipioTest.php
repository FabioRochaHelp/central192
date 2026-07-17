<?php

declare(strict_types=1);

use App\Livewire\Operations\IncidentCreate;
use App\Models\Incident;
use App\Models\Nature;
use App\Models\User;
use Database\Seeders\OperationalDemoSeeder;
use Livewire\Livewire;

beforeEach(function (): void {
    $this->seed(OperationalDemoSeeder::class);
});

test('operational user can create incident without municipio link at creation', function (): void {
    /** @var User $central */
    $central = User::query()->where('email', 'central@example.com')->firstOrFail();
    $nature = Nature::query()->firstOrFail();

    Livewire::actingAs($central)
        ->test(IncidentCreate::class)
        ->set('occurred_at', now()->format('Y-m-d\TH:i'))
        ->set('nature_id', $nature->id)
        ->set('description', 'Ocorrência de teste')
        ->set('address_line', 'Rua de Teste, 123')
        ->set('caller_phone', '11987654321')
        ->call('saveWithCallType', 'N')
        ->assertHasNoErrors()
        ->assertRedirect();

    $created = Incident::query()->where('description', 'Ocorrência de teste')->first();
    expect($created)->not->toBeNull()
        ->and($created->municipio_id)->toBeNull()
        ->and($created->primary_shift_id)->toBeNull();
});
