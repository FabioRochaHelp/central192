<?php

declare(strict_types=1);

namespace App\Integrations\Tracking\Providers;

use App\Integrations\Tracking\Contracts\TrackingProvider;
use App\Integrations\Tracking\Providers\Ssx\SsxCircuitBreaker;
use App\Integrations\Tracking\Providers\Ssx\SsxService;
use App\Models\Vehicle;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/** Adapter do SSX/SystemSatX para o contrato neutro TrackingProvider. */
final class SsxProvider implements TrackingProvider
{
    public function __construct(private readonly SsxService $ssx) {}

    public function healthy(): bool
    {
        return $this->ssx->ping();
    }

    public function circuitOpen(): bool
    {
        return SsxCircuitBreaker::isOpen();
    }

    public function positions(): Collection
    {
        try {
            $positions = $this->ssx->positions();
            SsxCircuitBreaker::recordSuccess();
        } catch (\Throwable $e) {
            SsxCircuitBreaker::recordFailure();
            throw $e;
        }

        return $positions;
    }

    public function route(string $unitReference, CarbonInterface $from, CarbonInterface $to): Collection
    {
        return $this->ssx->route($unitReference, $from, $to);
    }

    public function unitReferenceFor(Vehicle $vehicle): ?string
    {
        $code = $vehicle->ssx_integration_code;

        return ($code === null || $code === '') ? null : (string) $code;
    }
}
