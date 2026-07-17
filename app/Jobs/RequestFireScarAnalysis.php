<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Integrations\BurnScar\BurnScarService;
use App\Integrations\BurnScar\Exceptions\BurnScarException;
use App\Models\FireScarAnalysis;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Solicita ao serviço externo a análise de cicatriz de uma ocorrência/área.
 *
 * O {@see BurnScarService} cuida das transições de status e da persistência do
 * resultado; aqui apenas engatilhamos o processamento de forma assíncrona.
 */
final class RequestFireScarAnalysis implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 120;

    public function __construct(public readonly int $analysisId) {}

    public function handle(BurnScarService $service): void
    {
        $analysis = FireScarAnalysis::find($this->analysisId);

        if ($analysis === null) {
            return;
        }

        try {
            $service->process($analysis);
        } catch (BurnScarException) {
            // O serviço já registrou o status ERRO e a mensagem no registro.
        }
    }
}
