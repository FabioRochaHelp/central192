<?php

declare(strict_types=1);

use App\Console\Commands\VincularIbgeMunicipios;
use App\Livewire\Operations\Cadastro\MunicipioManage;
use App\Models\IbgeMunicipio;
use App\Models\Municipio;
use App\Models\User;
use Database\Seeders\OperationalDemoSeeder;
use Livewire\Livewire;

beforeEach(function (): void {
    $this->seed(OperationalDemoSeeder::class);
});

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------

function makeIbge(string $nome, string $uf): IbgeMunicipio
{
    static $counter = 9000000;

    return IbgeMunicipio::query()->create([
        'codigo_ibge' => (string) ++$counter,
        'nome'        => $nome,
        'uf'          => $uf,
    ]);
}

function centralUser(): User
{
    return User::query()->where('email', 'central@example.com')->firstOrFail();
}

// ---------------------------------------------------------------------------
// Criação com vínculo automático
// ---------------------------------------------------------------------------

test('criação de municipio vincula ibge_municipio_id automaticamente', function (): void {
    $ibge = makeIbge('Presidente Prudente', 'SP');

    Livewire::actingAs(centralUser())
        ->test(MunicipioManage::class)
        ->set('razao_social', 'Base SP')
        ->set('city', 'Presidente Prudente')
        ->set('state', 'SP')
        ->call('save')
        ->assertHasNoErrors();

    $municipio = Municipio::query()->where('razao_social', 'Base SP')->firstOrFail();

    expect($municipio->ibge_municipio_id)->toBe($ibge->id);
});

test('criação sem correspondência ibge deixa ibge_municipio_id nulo', function (): void {
    Livewire::actingAs(centralUser())
        ->test(MunicipioManage::class)
        ->set('razao_social', 'Base Fantasma')
        ->set('city', 'Cidade Inexistente')
        ->set('state', 'ZZ')
        ->call('save')
        ->assertHasNoErrors();

    $municipio = Municipio::query()->where('razao_social', 'Base Fantasma')->firstOrFail();

    expect($municipio->ibge_municipio_id)->toBeNull();
});

// ---------------------------------------------------------------------------
// Atualização com recálculo do vínculo
// ---------------------------------------------------------------------------

test('edição de municipio recalcula ibge_municipio_id', function (): void {
    $ibgeSp = makeIbge('São Paulo', 'SP');
    $ibgeRj = makeIbge('Rio de Janeiro', 'RJ');

    $municipio = Municipio::query()->create([
        'razao_social'      => 'Base para editar',
        'city'              => 'São Paulo',
        'state'             => 'SP',
        'active'            => true,
        'ibge_municipio_id' => $ibgeSp->id,
    ]);

    Livewire::actingAs(centralUser())
        ->test(MunicipioManage::class)
        ->call('edit', $municipio->id)
        ->set('city', 'Rio de Janeiro')
        ->set('state', 'RJ')
        ->call('save')
        ->assertHasNoErrors();

    expect($municipio->fresh()->ibge_municipio_id)->toBe($ibgeRj->id);
});

// ---------------------------------------------------------------------------
// Normalização na criação (acentos e caixa)
// ---------------------------------------------------------------------------

test('criação vincula mesmo com city em caixa alta e sem acento', function (): void {
    $ibge = makeIbge('São Paulo', 'SP');

    Livewire::actingAs(centralUser())
        ->test(MunicipioManage::class)
        ->set('razao_social', 'Base Caixa Alta')
        ->set('city', 'SAO PAULO')
        ->set('state', 'SP')
        ->call('save')
        ->assertHasNoErrors();

    $municipio = Municipio::query()->where('razao_social', 'Base Caixa Alta')->firstOrFail();

    expect($municipio->ibge_municipio_id)->toBe($ibge->id);
});

// ---------------------------------------------------------------------------
// Comando de backfill
// ---------------------------------------------------------------------------

test('comando vincular-ibge vincula municipios sem ibge_municipio_id', function (): void {
    $ibge = makeIbge('Campinas', 'SP');

    Municipio::query()->create([
        'razao_social' => 'Base Campinas',
        'city'         => 'Campinas',
        'state'        => 'SP',
        'active'       => true,
    ]);

    $this->artisan(VincularIbgeMunicipios::class)
        ->assertExitCode(0);

    $municipio = Municipio::query()->where('razao_social', 'Base Campinas')->firstOrFail();

    expect($municipio->ibge_municipio_id)->toBe($ibge->id);
});

test('comando vincular-ibge exibe relatório com totais corretos', function (): void {
    makeIbge('Sorocaba', 'SP');

    Municipio::query()->create([
        'razao_social' => 'Base Sorocaba',
        'city'         => 'Sorocaba',
        'state'        => 'SP',
        'active'       => true,
    ]);

    Municipio::query()->create([
        'razao_social' => 'Base Desconhecida',
        'city'         => 'Cidade Nenhuma',
        'state'        => 'ZZ',
        'active'       => true,
    ]);

    $this->artisan(VincularIbgeMunicipios::class)
        ->expectsOutputToContain('2')  // total analisados (exclui o do seeder que tem city='Demonstração')
        ->assertExitCode(0);
});

test('comando vincular-ibge ignora municipios ja vinculados', function (): void {
    $ibge = makeIbge('Santos', 'SP');

    $municipio = Municipio::query()->create([
        'razao_social'      => 'Base Santos',
        'city'              => 'Santos',
        'state'             => 'SP',
        'active'            => true,
        'ibge_municipio_id' => $ibge->id,
    ]);

    $this->artisan(VincularIbgeMunicipios::class)
        ->assertExitCode(0);

    // ibge_municipio_id não deve ser alterado
    expect($municipio->fresh()->ibge_municipio_id)->toBe($ibge->id);
});
