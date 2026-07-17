<?php

declare(strict_types=1);

namespace App\Integrations\Traccar;

use App\Integrations\Traccar\Exceptions\TraccarException;
use Carbon\CarbonInterface;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

final class TraccarClient
{
    private const string SESSION_CACHE_KEY = 'traccar.session_cookie';

    /** GET /api/server — sem auth, usado para health check. */
    public function serverInfo(): array
    {
        try {
            $response = $this->unauthenticatedClient()->get('server');
        } catch (ConnectionException $e) {
            throw new TraccarException('Traccar connection timed out', 408, $e);
        }

        $this->assertOk($response, 'server');

        return $response->json() ?? [];
    }

    /** GET /api/devices — lista todos os devices acessíveis ao usuário. */
    public function devices(): array
    {
        $response = $this->requestWithSessionFallback(fn (PendingRequest $client): Response => $client->get('devices'));

        $this->assertOk($response, 'devices');

        return $response->json() ?? [];
    }

    /** GET /api/positions — posições atuais. Filtra por deviceId se fornecido. */
    public function positions(?int $deviceId = null): array
    {
        $params = $deviceId !== null ? ['deviceId' => $deviceId] : [];

        $response = $this->requestWithSessionFallback(fn (PendingRequest $client): Response => $client->get('positions', $params));

        $this->assertOk($response, 'positions');

        return $response->json() ?? [];
    }

    /**
     * GET /api/positions — histórico de posições de um device num intervalo.
     *
     * @return list<array<string, mixed>>
     */
    public function positionsHistory(int $deviceId, string $from, string $to): array
    {
        $response = $this->requestWithSessionFallback(
            fn (PendingRequest $client): Response => $this->reportClient($client)->get('positions', [
                'deviceId' => $deviceId,
                'from' => $from,
                'to' => $to,
            ]),
        );

        $this->assertOk($response, 'positions');

        return $this->decodePositionList($response);
    }

    /**
     * GET /api/reports/route — percurso de um device num intervalo.
     *
     * @return list<array<string, mixed>>
     */
    public function route(int $deviceId, string $from, string $to): array
    {
        $response = $this->requestWithSessionFallback(
            fn (PendingRequest $client): Response => $this->reportClient($client)->get('reports/route', [
                'deviceId' => [$deviceId],
                'from' => $from,
                'to' => $to,
            ]),
        );

        $this->assertOk($response, 'reports/route');

        return $this->decodePositionList($response);
    }

    /** GET /api/reports/summary — resumo de uma viagem. */
    public function summary(int $deviceId, string $from, string $to): array
    {
        $response = $this->requestWithSessionFallback(
            fn (PendingRequest $client): Response => $this->reportClient($client)->get('reports/summary', [
                'deviceId' => [$deviceId],
                'from' => $from,
                'to' => $to,
            ]),
        );

        $this->assertOk($response, 'reports/summary');

        return $response->json() ?? [];
    }

    /** GET /api/reports/events — eventos de um device num intervalo. */
    public function events(int $deviceId, string $from, string $to): array
    {
        $response = $this->requestWithSessionFallback(
            fn (PendingRequest $client): Response => $this->reportClient($client)->get('reports/events', [
                'deviceId' => [$deviceId],
                'from' => $from,
                'to' => $to,
            ]),
        );

        $this->assertOk($response, 'reports/events');

        return $response->json() ?? [];
    }

    public static function formatUtcInstant(CarbonInterface $instant): string
    {
        return $instant->copy()->utc()->format('Y-m-d\TH:i:s.000\Z');
    }

    /**
     * @return array{status: int, body: mixed, raw: string}
     */
    public function normalize(Response $response): array
    {
        return [
            'status' => $response->status(),
            'body' => $response->json(),
            'raw' => $response->body(),
        ];
    }

    private function requestWithSessionFallback(callable $callback): Response
    {
        try {
            $response = $callback($this->client());
        } catch (ConnectionException $e) {
            throw new TraccarException('Traccar connection timed out', 408, $e);
        }

        if ($response->status() !== 401) {
            return $response;
        }

        Log::warning('Traccar auth failed, retrying with session cookie', [
            'auth_type' => config('traccar.auth_type'),
        ]);

        Cache::forget(self::SESSION_CACHE_KEY);

        try {
            return $callback($this->client($this->refreshSessionCookie()));
        } catch (ConnectionException $e) {
            throw new TraccarException('Traccar connection timed out', 408, $e);
        }
    }

    private function client(?string $cookie = null): PendingRequest
    {
        $request = $this->unauthenticatedClient();

        if ($cookie !== null) {
            return $request->withHeaders(['Cookie' => $cookie]);
        }

        return match (config('traccar.auth_type', 'basic')) {
            'bearer' => $request->withToken((string) config('traccar.api_key')),
            'session' => $request->withHeaders(['Cookie' => $this->sessionCookie()]),
            default => $request->withBasicAuth((string) config('traccar.username'), (string) config('traccar.password')),
        };
    }

    private function reportClient(PendingRequest $client): PendingRequest
    {
        $timeout = (int) config('traccar.report_timeout', max(30, (int) config('traccar.timeout', 10)));

        return $client
            ->timeout($timeout)
            ->accept('application/json')
            ->withHeaders(['Accept' => 'application/json']);
    }

    private function unauthenticatedClient(): PendingRequest
    {
        return Http::baseUrl($this->apiBaseUrl())
            ->timeout((int) config('traccar.timeout', 10))
            ->withOptions(['verify' => (bool) config('traccar.verify_ssl', true)])
            ->accept('application/json')
            ->withHeaders(['Accept' => 'application/json']);
    }

    private function apiBaseUrl(): string
    {
        $baseUrl = rtrim((string) config('traccar.base_url'), '/');

        if ($baseUrl === '') {
            throw new RuntimeException('Traccar base_url is not configured.');
        }

        if (app()->isProduction() && str_starts_with($baseUrl, 'http://')) {
            throw new RuntimeException('Traccar base_url must use HTTPS in production.');
        }

        return "{$baseUrl}/api";
    }

    private function sessionCookie(): string
    {
        $minutes = (int) config('traccar.session_cache_minutes', 25);

        return Cache::remember(self::SESSION_CACHE_KEY, now()->addMinutes($minutes), fn (): string => $this->createSession());
    }

    private function refreshSessionCookie(): string
    {
        $cookie = $this->createSession();
        $minutes = (int) config('traccar.session_cache_minutes', 25);

        Cache::put(self::SESSION_CACHE_KEY, $cookie, now()->addMinutes($minutes));

        return $cookie;
    }

    private function createSession(): string
    {
        $response = $this->unauthenticatedClient()
            ->asForm()
            ->post('session', [
                'email' => (string) config('traccar.session_email'),
                'password' => (string) config('traccar.session_password'),
            ]);

        if (! $response->successful()) {
            throw new RuntimeException("Traccar [session] login failed HTTP {$response->status()}");
        }

        $cookie = $this->extractSessionCookie($response);

        if ($cookie === '') {
            throw new RuntimeException('Traccar [session] missing Set-Cookie header');
        }

        return $cookie;
    }

    private function extractSessionCookie(Response $response): string
    {
        $setCookies = $response->header('Set-Cookie');

        if ($setCookies === null || $setCookies === []) {
            return '';
        }

        if (! is_array($setCookies)) {
            $setCookies = [$setCookies];
        }

        $parts = [];

        foreach ($setCookies as $setCookie) {
            $segment = explode(';', (string) $setCookie)[0];

            if ($segment !== '') {
                $parts[] = trim($segment);
            }
        }

        return implode('; ', $parts);
    }

    /** @return list<array<string, mixed>> */
    private function decodePositionList(Response $response): array
    {
        $decoded = $response->json();

        if (! is_array($decoded)) {
            throw new TraccarException(
                'Traccar response is not JSON',
                $response->status(),
                responseBody: $response->body(),
            );
        }

        return $decoded;
    }

    private function assertOk(Response $response, string $endpoint): void
    {
        if ($response->successful()) {
            return;
        }

        Log::warning('Traccar request failed', [
            'endpoint' => $endpoint,
            'status' => $response->status(),
            'body' => $response->body(),
        ]);

        throw new TraccarException(
            "Traccar [{$endpoint}] returned HTTP {$response->status()}",
            $response->status(),
            responseBody: $response->body(),
        );
    }
}
