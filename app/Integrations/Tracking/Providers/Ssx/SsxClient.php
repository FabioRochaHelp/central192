<?php

declare(strict_types=1);

namespace App\Integrations\Tracking\Providers\Ssx;

use App\Integrations\Tracking\Providers\Ssx\Exceptions\SsxException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Cliente HTTP do SSX/SystemSatX. Autentica via JWT Bearer (POST /Login),
 * cacheia o token e refaz o login automaticamente em respostas 401.
 *
 * @see https://integration.systemsatx.com.br/index.html
 */
final class SsxClient
{
    private const string TOKEN_CACHE_KEY = 'ssx.token';

    /** Health check: tenta obter (ou renovar) o token de acesso. */
    public function health(): bool
    {
        try {
            return $this->token() !== '';
        } catch (\Throwable $e) {
            Log::warning('SSX health check failed', ['error' => $e->getMessage()]);

            return false;
        }
    }

    /**
     * POST /Controlws/LastPosition/GetLastPositions — última posição de cada unidade.
     * Endpoint próprio para "posição atual" (uma linha por rastreador, sem teto de
     * histórico). Filtros QueryCondition são opcionais.
     *
     * @param  list<array{PropertyName: string, Condition: string, Value: mixed}>  $filter
     * @return list<array<string, mixed>>
     */
    public function lastPositions(array $filter = []): array
    {
        $body = $filter === [] ? new \stdClass : ['Filter' => $filter];

        $response = $this->requestWithTokenFallback(
            fn (PendingRequest $client): Response => $client->post('Controlws/LastPosition/GetLastPositions', $body),
        );

        if ($response->status() === 204) {
            return [];
        }

        $this->assertOk($response, 'LastPosition/GetLastPositions');

        $decoded = $response->json();

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * POST /v3/Tracking/PositionHistory/List — histórico de posições.
     * Recebe um array de filtros QueryCondition; pelo menos um é obrigatório.
     * Usado para o percurso da ocorrência (intervalo de tempo).
     *
     * @param  list<array{PropertyName: string, Condition: string, Value: mixed}>  $conditions
     * @return list<array<string, mixed>>
     */
    public function positionHistory(array $conditions): array
    {
        $response = $this->requestWithTokenFallback(
            fn (PendingRequest $client): Response => $client->post('v3/Tracking/PositionHistory/List', $conditions),
        );

        // 204 = processado sem conteúdo (nenhuma posição no filtro).
        if ($response->status() === 204) {
            return [];
        }

        $this->assertOk($response, 'PositionHistory/List');

        $decoded = $response->json();

        return is_array($decoded) ? $decoded : [];
    }

    private function requestWithTokenFallback(callable $callback): Response
    {
        try {
            $response = $callback($this->authenticatedClient($this->token()));
        } catch (ConnectionException $e) {
            throw new SsxException('SSX connection timed out', 408, $e);
        }

        if ($response->status() !== 401) {
            return $response;
        }

        Log::warning('SSX token rejeitado, renovando login');
        Cache::forget(self::TOKEN_CACHE_KEY);

        try {
            return $callback($this->authenticatedClient($this->token()));
        } catch (ConnectionException $e) {
            throw new SsxException('SSX connection timed out', 408, $e);
        }
    }

    private function token(): string
    {
        $cached = Cache::get(self::TOKEN_CACHE_KEY);

        if (is_string($cached) && $cached !== '') {
            return $cached;
        }

        return $this->login();
    }

    /** POST /Login — gera o token JWT e o guarda em cache até expirar. */
    public function login(): string
    {
        $params = array_filter([
            'Username' => (string) config('ssx.username'),
            'Password' => (string) config('ssx.password'),
            'Hashcentral' => (string) config('ssx.hash_central'),
            'HashAuth' => (string) config('ssx.hash_auth'),
            'ClientIntegrationCodeBus' => (string) config('ssx.client_integration_code_bus'),
        ], static fn (string $value): bool => $value !== '');

        try {
            $response = $this->baseClient()->withQueryParameters($params)->post('Login');
        } catch (ConnectionException $e) {
            throw new SsxException('SSX connection timed out', 408, $e);
        }

        $this->assertOk($response, 'Login');

        $token = (string) ($response->json('AccessToken') ?? '');

        if ($token === '') {
            throw new SsxException('SSX login não retornou AccessToken', $response->status(), responseBody: $response->body());
        }

        $expiresIn = (int) ($response->json('ExpiresIn') ?? 0);
        $ttlSeconds = $this->resolveTokenTtlSeconds($expiresIn);

        Cache::put(self::TOKEN_CACHE_KEY, $token, now()->addSeconds($ttlSeconds));

        return $token;
    }

    /** Ticks .NET (100ns) entre 0001-01-01 e o epoch Unix (1970-01-01). */
    private const int DOTNET_TICKS_AT_UNIX_EPOCH = 621355968000000000;

    /**
     * O SSX devolve ExpiresIn como o instante absoluto de expiração em ticks .NET
     * (não em segundos). Converte para um TTL em segundos, com folga e teto de
     * segurança; o re-login automático em 401 cobre expirações antecipadas.
     */
    private function resolveTokenTtlSeconds(int $expiresIn): int
    {
        $fallbackSeconds = max(1, (int) config('ssx.token_cache_minutes', 25)) * 60;
        $maxSeconds = 12 * 60 * 60;

        if ($expiresIn <= 0) {
            return $fallbackSeconds;
        }

        $now = now()->timestamp;

        if ($expiresIn >= self::DOTNET_TICKS_AT_UNIX_EPOCH) {
            // Instante absoluto em ticks .NET.
            $expiresUnix = (int) (($expiresIn - self::DOTNET_TICKS_AT_UNIX_EPOCH) / 10_000_000);
            $ttl = $expiresUnix - $now;
        } elseif ($expiresIn > $now) {
            // Instante absoluto em timestamp Unix.
            $ttl = $expiresIn - $now;
        } else {
            // Duração em segundos.
            $ttl = $expiresIn;
        }

        $ttl -= 60;

        if ($ttl < 60) {
            return $fallbackSeconds;
        }

        return min($ttl, $maxSeconds);
    }

    private function authenticatedClient(string $token): PendingRequest
    {
        return $this->baseClient()->withToken($token);
    }

    private function baseClient(): PendingRequest
    {
        return Http::baseUrl($this->apiBaseUrl())
            ->timeout((int) config('ssx.timeout', 15))
            ->withOptions(['verify' => (bool) config('ssx.verify_ssl', true)])
            ->acceptJson()
            ->asJson();
    }

    private function apiBaseUrl(): string
    {
        $baseUrl = rtrim((string) config('ssx.base_url'), '/');

        if ($baseUrl === '') {
            throw new RuntimeException('SSX base_url is not configured.');
        }

        if (app()->isProduction() && str_starts_with($baseUrl, 'http://')) {
            throw new RuntimeException('SSX base_url must use HTTPS in production.');
        }

        return $baseUrl;
    }

    private function assertOk(Response $response, string $endpoint): void
    {
        if ($response->successful()) {
            return;
        }

        Log::warning('SSX request failed', [
            'endpoint' => $endpoint,
            'status' => $response->status(),
            'body' => $response->body(),
        ]);

        throw new SsxException(
            "SSX [{$endpoint}] returned HTTP {$response->status()}",
            $response->status(),
            responseBody: $response->body(),
        );
    }
}
