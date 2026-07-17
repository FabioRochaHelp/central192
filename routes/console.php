<?php

use App\Domain\Fire\Actions\ImportarFocosSateliteAction;
use App\Integrations\Traccar\TraccarCircuitBreaker;
use App\Integrations\Traccar\TraccarService;
use App\Integrations\Tracking\Contracts\TrackingProvider;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('focos:importar-satelite {--all : Importa todos os pendentes em lotes grandes}', function (ImportarFocosSateliteAction $action): int {
    if ($this->option('all')) {
        $this->info('Importação em massa iniciada...');
        $imported = $action->importAll();
        $this->info("Importação concluída: {$imported} foco(s).");

        return self::SUCCESS;
    }

    $imported = $action->execute();
    $this->info("Lote importado: {$imported} foco(s).");

    return self::SUCCESS;
})->purpose('Importa focos do banco satélite (fire_monitor)');

Artisan::command('tracking:ping', function (TrackingProvider $provider): int {
    $name = (string) config('tracking.provider', 'traccar');

    if ($provider->circuitOpen()) {
        $this->warn(__('Circuit breaker aberto — chamadas ao :provider estão pausadas temporariamente.', ['provider' => $name]));

        return self::FAILURE;
    }

    if (! $provider->healthy()) {
        $this->error(__('Provedor de rastreamento :provider indisponível ou não configurado.', ['provider' => $name]));

        return self::FAILURE;
    }

    $this->info(__('Provedor de rastreamento :provider respondeu com sucesso.', ['provider' => $name]));

    return self::SUCCESS;
})->purpose('Verifica conectividade com o provedor de rastreamento ativo');

Artisan::command('traccar:ping {--devices : Lista devices após o health check}', function (TraccarService $traccar): int {
    if (TraccarCircuitBreaker::isOpen()) {
        $this->warn(__('Circuit breaker aberto — chamadas ao Traccar estão pausadas temporariamente.'));

        return self::FAILURE;
    }

    if (! $traccar->ping()) {
        $this->error(__('Traccar indisponível ou não configurado.'));

        return self::FAILURE;
    }

    $this->info(__('Traccar respondeu com sucesso.'));

    if ($this->option('devices')) {
        $devices = $traccar->devices();
        $threshold = (int) config('traccar.device_online_threshold_seconds', 180);

        $this->line(__('Conectividade = comunicação nos últimos :seconds s (não apenas o status da API).', [
            'seconds' => $threshold,
        ]));

        $this->table(
            ['ID', 'Nome', 'Unique ID', 'Conectividade', 'Última comunicação', 'Status API'],
            $devices->map(fn ($device): array => [
                $device->id,
                $device->name,
                $device->uniqueId,
                $device->isReportingAt() ? 'online' : 'offline',
                $device->lastUpdate ?? '—',
                $device->status,
            ])->all(),
        );
    }

    return self::SUCCESS;
})->purpose('Verifica conectividade com o servidor Traccar');
