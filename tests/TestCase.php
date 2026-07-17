<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Laravel\Fortify\Features;
use Livewire\LivewireServiceProvider;

abstract class TestCase extends BaseTestCase
{
    protected function skipUnlessFortifyHas(string $feature, ?string $message = null): void
    {
        if (! Features::enabled($feature)) {
            $this->markTestSkipped($message ?? "Fortify feature [{$feature}] is not enabled.");
        }
    }

    /**
     * Pular teste se estiver no CI (GitHub Actions)
     */
    protected function skipIfCI(?string $message = null): void
    {
        if (getenv('CI') === 'true' || getenv('GITHUB_ACTIONS') === 'true') {
            $this->markTestSkipped($message ?? 'Test skipped in CI environment.');
        }
    }

    /**
     * Pular teste do Livewire se estiver no CI
     */
    protected function skipLivewireTestInCI(): void
    {
        if (getenv('CI') === 'true' || getenv('GITHUB_ACTIONS') === 'true') {
            $this->markTestSkipped('Livewire tests skipped in CI environment due to JavaScript dependencies.');
        }
    }

    protected function setUp(): void
    {
        parent::setUp();

        // Reinicia o contexto operacional estático entre testes para evitar
        // que o ID de municipio de um teste anterior vaze para o próximo via
        // global scope do BelongsToMunicipio.
        \App\Support\Operations\CurrentMunicipio::set(null);

        if (getenv('CI') === 'true') {
            config(['app.debug' => true]);
            config(['livewire.asset_url' => '/']);
        }
    }
}
