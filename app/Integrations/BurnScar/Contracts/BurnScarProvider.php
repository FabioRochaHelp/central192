<?php

declare(strict_types=1);

namespace App\Integrations\BurnScar\Contracts;

use App\Integrations\BurnScar\DTOs\BurnScarResult;
use App\Integrations\BurnScar\Exceptions\BurnScarException;
use App\Models\FireScarAnalysis;

/** Fonte da análise de cicatriz (serviço externo ou estimativa local por focos). */
interface BurnScarProvider
{
    /**
     * Produz a cicatriz para a análise informada.
     *
     * @throws BurnScarException em caso de falha (o service marca a análise como ERRO).
     */
    public function analyze(FireScarAnalysis $analysis): BurnScarResult;

    /** Identificador curto do provedor (ex.: 'external', 'focos'). */
    public function name(): string;
}
