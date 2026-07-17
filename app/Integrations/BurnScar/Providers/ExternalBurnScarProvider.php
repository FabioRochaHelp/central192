<?php

declare(strict_types=1);

namespace App\Integrations\BurnScar\Providers;

use App\Integrations\BurnScar\BurnScarCircuitBreaker;
use App\Integrations\BurnScar\BurnScarClient;
use App\Integrations\BurnScar\Contracts\BurnScarProvider;
use App\Integrations\BurnScar\DTOs\BurnScarRequest;
use App\Integrations\BurnScar\DTOs\BurnScarResult;
use App\Integrations\BurnScar\Exceptions\BurnScarException;
use App\Models\FireScarAnalysis;

/** Provedor que delega o cálculo a um serviço externo de sensoriamento remoto. */
final class ExternalBurnScarProvider implements BurnScarProvider
{
    public function __construct(private readonly BurnScarClient $client) {}

    public function analyze(FireScarAnalysis $analysis): BurnScarResult
    {
        if (BurnScarCircuitBreaker::isOpen()) {
            throw new BurnScarException('Burn scar circuit breaker is open');
        }

        try {
            $result = $this->client->analyze(BurnScarRequest::fromModel($analysis));
        } catch (BurnScarException $e) {
            BurnScarCircuitBreaker::recordFailure();

            throw $e;
        }

        BurnScarCircuitBreaker::recordSuccess();

        return $result;
    }

    public function name(): string
    {
        return 'external';
    }
}
