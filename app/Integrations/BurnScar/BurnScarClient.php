<?php

declare(strict_types=1);

namespace App\Integrations\BurnScar;

use App\Integrations\BurnScar\DTOs\BurnScarRequest;
use App\Integrations\BurnScar\DTOs\BurnScarResult;
use App\Integrations\BurnScar\Exceptions\BurnScarException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/** Cliente HTTP do serviço externo de cicatriz de incêndio. */
final class BurnScarClient
{
    /**
     * Envia a requisição de análise e devolve a cicatriz calculada.
     *
     * @throws BurnScarException
     */
    public function analyze(BurnScarRequest $request): BurnScarResult
    {
        $path = trim((string) config('burnscar.analyze_path', 'analyze'), '/');

        try {
            $response = $this->client()->post($path, $request->toArray());
        } catch (ConnectionException $e) {
            throw new BurnScarException('Burn scar service connection timed out', 408, $e);
        }

        $this->assertOk($response);

        $payload = $response->json();

        if (! is_array($payload)) {
            throw new BurnScarException('Burn scar service returned an invalid payload', $response->status(), responseBody: $response->body());
        }

        return BurnScarResult::fromResponse($payload);
    }

    private function client(): PendingRequest
    {
        $baseUrl = (string) config('burnscar.base_url');

        if ($baseUrl === '') {
            throw new BurnScarException('BURNSCAR_BASE_URL is not configured');
        }

        $client = Http::baseUrl($baseUrl)
            ->timeout((int) config('burnscar.timeout', 60))
            ->acceptJson()
            ->asJson();

        if (! (bool) config('burnscar.verify_ssl', true)) {
            $client = $client->withoutVerifying();
        }

        $token = (string) config('burnscar.token');

        if ($token !== '') {
            $client = $client->withToken($token);
        }

        return $client;
    }

    private function assertOk(Response $response): void
    {
        if ($response->successful()) {
            return;
        }

        throw new BurnScarException(
            sprintf('Burn scar service responded with HTTP %d', $response->status()),
            $response->status(),
            responseBody: $response->body(),
        );
    }
}
