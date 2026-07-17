<?php

declare(strict_types=1);

namespace App\Integrations\BurnScar;

use App\Domain\Fire\Enums\FireScarSeverity;
use App\Domain\Fire\Enums\FireScarStatus;
use App\Integrations\BurnScar\Contracts\BurnScarProvider;
use App\Integrations\BurnScar\Exceptions\BurnScarException;
use App\Models\FireScarAnalysis;
use Illuminate\Support\Facades\Log;

/**
 * Orquestra a análise de cicatriz: gerencia as transições de status e persiste o
 * resultado. O cálculo é delegado ao {@see BurnScarProvider} configurado
 * (serviço externo ou estimativa local por focos de calor).
 */
final class BurnScarService
{
    public function __construct(private readonly BurnScarProvider $provider) {}

    /**
     * Processa uma análise pendente. Atualiza status e persiste o resultado.
     *
     * @throws BurnScarException quando o provedor falha.
     */
    public function process(FireScarAnalysis $analysis): FireScarAnalysis
    {
        $analysis->forceFill([
            'status' => FireScarStatus::Processando,
            'requested_at' => $analysis->requested_at ?? now(),
            'error_message' => null,
        ])->save();

        try {
            $result = $this->provider->analyze($analysis);
        } catch (BurnScarException $e) {
            $analysis->forceFill([
                'status' => FireScarStatus::Erro,
                'error_message' => $e->getMessage(),
                'completed_at' => now(),
            ])->save();

            Log::warning('Burn scar analysis failed', [
                'analysis_id' => $analysis->getKey(),
                'provider' => $this->provider->name(),
                'message' => $e->getMessage(),
            ]);

            throw $e;
        }

        $attributes = $result->toModelAttributes();

        // Normaliza a classe de severidade (rótulo canônico) quando possível.
        $severity = FireScarSeverity::tryFromLabel($attributes['severity_class'] ?? null)
            ?? ($attributes['dnbr'] !== null ? FireScarSeverity::fromDnbr((float) $attributes['dnbr']) : null);

        if ($severity !== null) {
            $attributes['severity_class'] = $severity->value;
        }

        $analysis->forceFill([
            ...$attributes,
            'status' => FireScarStatus::Concluido,
            'completed_at' => now(),
        ])->save();

        return $analysis;
    }
}
