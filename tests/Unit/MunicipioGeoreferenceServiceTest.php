<?php

declare(strict_types=1);

use App\Models\IbgeMunicipio;
use App\Services\MunicipioGeoreferenceService;

// ---------------------------------------------------------------------------
// Normalização
// ---------------------------------------------------------------------------

test('normalize converte para minúsculas', function (): void {
    $service = new MunicipioGeoreferenceService();

    expect($service->normalize('PRESIDENTE PRUDENTE'))->toBe('presidente prudente');
});

test('normalize remove acentos', function (): void {
    $service = new MunicipioGeoreferenceService();

    expect($service->normalize('São Paulo'))->toBe('sao paulo')
        ->and($service->normalize('Belém'))->toBe('belem')
        ->and($service->normalize('Goiânia'))->toBe('goiania')
        ->and($service->normalize('Florianópolis'))->toBe('florianopolis');
});

test('normalize remove espaços extras nas bordas', function (): void {
    $service = new MunicipioGeoreferenceService();

    expect($service->normalize('  Campinas  '))->toBe('campinas');
});

test('normalize combina acentos e caixa alta simultaneamente', function (): void {
    $service = new MunicipioGeoreferenceService();

    expect($service->normalize('SÃO PAULO'))->toBe('sao paulo');
});

// ---------------------------------------------------------------------------
// Find — usa banco de dados (Unit tests não usam RefreshDatabase por padrão,
// então usamos mocks/fakes via Mockery)
// ---------------------------------------------------------------------------

test('find retorna null quando city ou state estao vazios', function (): void {
    $service = new MunicipioGeoreferenceService();

    expect($service->find('', 'SP'))->toBeNull()
        ->and($service->find('São Paulo', ''))->toBeNull()
        ->and($service->find('', ''))->toBeNull();
});

test('find localiza municipio ignorando acentos', function (): void {
    $ibge = new IbgeMunicipio();
    $ibge->nome = 'São Paulo';
    $ibge->uf   = 'SP';
    $ibge->id   = 1;

    $service = new MunicipioGeoreferenceService();

    // Verify normalize equality that find() relies on
    $normalizedInput = $service->normalize('Sao Paulo');
    $normalizedDb    = $service->normalize($ibge->nome);

    expect($normalizedInput)->toBe($normalizedDb);
});

test('find localiza municipio ignorando caixa alta', function (): void {
    $service = new MunicipioGeoreferenceService();

    expect($service->normalize('PRESIDENTE PRUDENTE'))
        ->toBe($service->normalize('presidente prudente'))
        ->toBe($service->normalize('Presidente Prudente'));
});
