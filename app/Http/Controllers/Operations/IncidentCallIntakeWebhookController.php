<?php

declare(strict_types=1);

namespace App\Http\Controllers\Operations;

use App\Domain\Operations\Enums\CallIntakeWebhookType;
use App\Domain\Operations\Enums\OperationalCallAlertStatus;
use App\Domain\Operations\Events\OperationalCallAlertClusterUpdated;
use App\Domain\Operations\Events\OperationalCallAlertReceived;
use App\Domain\Operations\Events\OperationalCallIntakeReceived;
use App\Http\Controllers\Controller;
use App\Models\OperationalCallAlert;
use App\Support\Operations\IncidentPhoneNormalizer;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * Recebe chamadas externas (PBX) e roteia conforme `type`:
 * - `alert`: marcação clicável no mapa tático (abortar ou criar ocorrência)
 * - `incident`: abre modal de cadastro via WebSocket (fluxo legado `nova-chamada`)
 */
final class IncidentCallIntakeWebhookController extends Controller
{
    /** GET — confirma que a URL do webhook está acessível sem exigir token. */
    public function ping(): JsonResponse
    {
        return response()->json([
            'status' => 'ok',
            'webhook' => 'incident-intake',
        ]);
    }

    public function __invoke(Request $request): JsonResponse
    {
        $this->assertWebhookSecret($request);

        $validated = $request->validate($this->intakeValidationRules());

        $type = CallIntakeWebhookType::from($validated['type']);

        if ($type === CallIntakeWebhookType::Alert) {
            $request->validate([
                'latitude' => ['required', 'numeric', 'between:-90,90'],
                'longitude' => ['required', 'numeric', 'between:-180,180'],
            ]);
        }

        $phone = IncidentPhoneNormalizer::normalize($validated['phone']);
        if (! IncidentPhoneNormalizer::passesMinimumLength($phone)) {
            return response()->json(
                ['message' => __('O número informado em «phone» deve ter ao menos 8 dígitos.')],
                422,
            );
        }

        $ttl = match ($type) {
            CallIntakeWebhookType::Alert => now()->addDays((int) config('operations.call_alert_ttl_days', 3)),
            CallIntakeWebhookType::Incident => now()->addMinutes((int) config('operations.call_intake_signed_url_ttl_minutes', 30)),
        };
        $expiresAtIso = $ttl->toIso8601String();
        $callReceivedAtIso = isset($validated['call_received_at'])
            ? CarbonImmutable::parse((string) $validated['call_received_at'])->toIso8601String()
            : null;

        $query = array_filter(
            [
                'phone' => $phone,
                'name' => $validated['caller_name'] ?? null,
                'lat' => isset($validated['latitude']) ? (string) $validated['latitude'] : null,
                'lng' => isset($validated['longitude']) ? (string) $validated['longitude'] : null,
                'received_at' => $callReceivedAtIso,
                'ref' => $validated['external_reference'] ?? null,
                'uniqueid' => $validated['uniqueid'] ?? null,
            ],
            static fn (?string $v): bool => $v !== null && $v !== '',
        );

        $formUrl = URL::temporarySignedRoute('operations.incidents.create', $ttl, $query);

        if ($type === CallIntakeWebhookType::Alert) {
            return $this->handleAlertIntake(
                phone: $phone,
                formUrl: $formUrl,
                expiresAtIso: $expiresAtIso,
                validated: $validated,
                callReceivedAtIso: $callReceivedAtIso,
            );
        }

        return $this->handleIncidentIntake(
            phone: $phone,
            formUrl: $formUrl,
            expiresAtIso: $expiresAtIso,
            validated: $validated,
            callReceivedAtIso: $callReceivedAtIso,
        );
    }

    private function assertWebhookSecret(Request $request): void
    {
        $configuredSecret = config('operations.call_webhook_secret');
        if (! is_string($configuredSecret) || $configuredSecret === '') {
            abort(config('app.env') === 'production' ? 503 : 422, __('Webhook de chamada não configurado (OPERATIONS_CALL_WEBHOOK_SECRET).'));
        }

        $provided = (string) $request->header('X-Webhook-Secret', '');
        if ($provided === '') {
            abort(401, __('Cabeçalho X-Webhook-Secret ausente.'));
        }
        if (! hash_equals($configuredSecret, $provided)) {
            abort(403, __('Token inválido.'));
        }
    }

    /** @param array<string, mixed> $validated */
    private function handleAlertIntake(
        string $phone,
        string $formUrl,
        string $expiresAtIso,
        array $validated,
        ?string $callReceivedAtIso,
    ): JsonResponse {
        $metadata = $this->normalizedAlertMetadata($validated);

        $alert = OperationalCallAlert::query()->create([
            'id' => (string) Str::uuid(),
            'phone' => $phone,
            'caller_name' => $validated['caller_name'] ?? null,
            'latitude' => $validated['latitude'],
            'longitude' => $validated['longitude'],
            'call_received_at' => $callReceivedAtIso !== null
                ? CarbonImmutable::parse($callReceivedAtIso)
                : null,
            'external_reference' => $validated['external_reference'] ?? null,
            'pabx_uniqueid' => $validated['uniqueid'] ?? null,
            'metadata' => $metadata === [] ? null : $metadata,
            'form_url' => $formUrl,
            'expires_at' => CarbonImmutable::parse($expiresAtIso),
            'status' => OperationalCallAlertStatus::Pending,
        ]);

        if (config('operations.broadcast_call_intake', true)) {
            OperationalCallAlertReceived::dispatch($alert);
            OperationalCallAlertClusterUpdated::dispatchForLocation(
                (float) $alert->latitude,
                (float) $alert->longitude,
            );
        }

        return response()->json([
            'type' => CallIntakeWebhookType::Alert->value,
            'alert_id' => $alert->id,
            'expires_at' => $expiresAtIso,
        ]);
    }

    /** @param array<string, mixed> $validated */
    private function handleIncidentIntake(
        string $phone,
        string $formUrl,
        string $expiresAtIso,
        array $validated,
        ?string $callReceivedAtIso,
    ): JsonResponse {
        if (config('operations.broadcast_call_intake', true)) {
            OperationalCallIntakeReceived::dispatch(
                $formUrl,
                $phone,
                $expiresAtIso,
                $validated['caller_name'] ?? null,
                isset($validated['latitude']) ? (string) $validated['latitude'] : null,
                isset($validated['longitude']) ? (string) $validated['longitude'] : null,
                $callReceivedAtIso,
                $validated['external_reference'] ?? null,
                $validated['uniqueid'] ?? null,
            );
        }

        return response()->json([
            'type' => CallIntakeWebhookType::Incident->value,
            'form_url' => $formUrl,
            'expires_at' => $expiresAtIso,
        ]);
    }

    /** @return array<string, array<int, mixed>> */
    private function intakeValidationRules(): array
    {
        return [
            'type' => ['required', 'string', Rule::enum(CallIntakeWebhookType::class)],
            'phone' => ['required', 'string', 'max:32'],
            'caller_name' => ['nullable', 'string', 'max:255'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'call_received_at' => ['nullable', 'date'],
            'external_reference' => ['nullable', 'string', 'max:500'],
            'uniqueid' => ['nullable', 'string', 'max:128'],
            'metadata' => ['nullable', 'array'],
            'metadata.temperature' => ['nullable', 'numeric'],
            'metadata.humidity' => ['nullable', 'numeric'],
            'metadata.wind_speed' => ['nullable', 'numeric'],
            'metadata.wind_direction' => ['nullable', 'numeric'],
            'metadata.wind_direction_text' => ['nullable', 'string', 'max:32'],
            'metadata.air_temperature' => ['nullable', 'numeric'],
            'metadata.rain' => ['nullable', 'numeric'],
        ];
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, float|string>
     */
    private function normalizedAlertMetadata(array $validated): array
    {
        /** @var array<string, mixed> $metadata */
        $metadata = is_array($validated['metadata'] ?? null) ? $validated['metadata'] : [];

        $normalized = [];

        foreach (['temperature', 'humidity', 'wind_speed', 'wind_direction', 'air_temperature', 'rain'] as $key) {
            if (! array_key_exists($key, $metadata) || $metadata[$key] === null || $metadata[$key] === '') {
                continue;
            }

            $normalized[$key] = (float) $metadata[$key];
        }

        if (array_key_exists('wind_direction_text', $metadata) && $metadata['wind_direction_text'] !== null) {
            $windDirectionText = trim((string) $metadata['wind_direction_text']);
            if ($windDirectionText !== '') {
                $normalized['wind_direction_text'] = $windDirectionText;
            }
        }

        return $normalized;
    }
}
