<?php

declare(strict_types=1);

namespace App\Integrations\Tracking\Contracts;

use App\Integrations\Tracking\DTOs\TrackingPosition;
use App\Integrations\Tracking\DTOs\TrackingRoutePoint;
use App\Models\Vehicle;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * Provedor de rastreamento de veículos. Implementado por adapters
 * (Traccar, SSX/SystemSatX) e selecionado por config('tracking.provider').
 */
interface TrackingProvider
{
    /** Verifica conectividade/autenticação com o provedor. */
    public function healthy(): bool;

    /** O circuit breaker do provedor está aberto (chamadas pausadas)? */
    public function circuitOpen(): bool;

    /**
     * Posições atuais de todas as unidades acessíveis.
     *
     * @return Collection<int, TrackingPosition>
     */
    public function positions(): Collection;

    /**
     * Percurso histórico de uma unidade num intervalo (replay da ocorrência).
     *
     * @return Collection<int, TrackingRoutePoint>
     */
    public function route(string $unitReference, CarbonInterface $from, CarbonInterface $to): Collection;

    /**
     * Referência da unidade nesse provedor para um Vehicle
     * (device_id no Traccar, ssx_integration_code no SSX). Null quando não vinculado.
     */
    public function unitReferenceFor(Vehicle $vehicle): ?string;
}
