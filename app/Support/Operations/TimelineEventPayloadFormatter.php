<?php

declare(strict_types=1);

namespace App\Support\Operations;

use App\Domain\Operations\Enums\CallType;
use App\Domain\Operations\Enums\DispatchStage;
use App\Domain\Operations\Enums\IncidentReportModality;
use App\Domain\Operations\Enums\ManchesterRisk;
use App\Domain\Operations\Enums\RegulationDecision;
use App\Domain\Operations\Enums\RegulationResource;
use App\Models\IncidentEvent;
use App\Models\Vehicle;
use Illuminate\Support\Str;

/** Converte o payload JSON de um evento da timeline em linhas legíveis (label/valor). */
final class TimelineEventPayloadFormatter
{
    /** @var array<int, array{prefix: string, base: ?string}> Memo de viaturas resolvidas no request. */
    private static array $vehicleCache = [];

    /** Chaves técnicas que não agregam ao card auditável (IDs internos, texto exibido à parte). */
    private const HIDDEN_KEYS = [
        'operator_user_id',
        'user_id',
        'regulator_user_id',
        'shift_id',
        'incident_dispatch_id',
        'victim_id',
        'report_id',
        'prescription_id',
        'primary_shift_id',
        'text',
    ];

    /** @return list<array{label: string, value: string, hint?: string}> */
    public static function rows(IncidentEvent $event): array
    {
        $payload = is_array($event->payload) ? $event->payload : [];

        if ($payload === []) {
            return [];
        }

        return in_array($event->event_key, ['dispatch_contact_attempted', 'dispatch_contact_failed'], true)
            ? self::contactRows($payload)
            : self::genericRows($payload);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return list<array{label: string, value: string, hint?: string}>
     */
    private static function contactRows(array $payload): array
    {
        $rows = [];

        // Viatura acionada (a selecionada no empenho) com a base em texto de apoio.
        $selected = self::resolveVehicle($payload['vehicle_id'] ?? null);
        if ($selected !== null) {
            $rows[] = array_filter([
                'label' => __('Viatura acionada'),
                'value' => $selected['prefix'],
                'hint' => $selected['base'],
            ], static fn ($value): bool => $value !== null);
        }

        if (! empty($payload['contact_method'])) {
            $rows[] = ['label' => __('Método de contato'), 'value' => self::contactMethod((string) $payload['contact_method'])];
        }

        if (! empty($payload['contact_details'])) {
            $rows[] = ['label' => __('Número / ramal / link'), 'value' => (string) $payload['contact_details']];
        }

        $rows[] = [
            'label' => __('Resultado'),
            'value' => ! empty($payload['successful']) ? __('Contato efetuado') : __('Não foi possível contatar'),
        ];

        if (! empty($payload['reason'])) {
            $rows[] = ['label' => __('Motivo'), 'value' => (string) $payload['reason']];
        }

        $nearest = $payload['nearest_vehicle'] ?? null;
        if (is_array($nearest)) {
            $prefix = trim((string) ($nearest['vehicle_prefix'] ?? ''));
            $plate = trim((string) ($nearest['vehicle_plate'] ?? ''));

            // Só mostra a "mais próxima" quando for diferente da viatura acionada.
            $isDifferent = ($nearest['vehicle_id'] ?? null) !== ($payload['vehicle_id'] ?? null);
            if ($prefix !== '' && $isDifferent) {
                $rows[] = [
                    'label' => __('Viatura mais próxima'),
                    'value' => $plate !== '' ? $prefix.' · '.$plate : $prefix,
                ];
            }

            $staff = $nearest['responsible_staff'] ?? [];
            if (is_array($staff) && $staff !== []) {
                $names = array_values(array_filter(array_map(
                    static fn ($member): ?string => is_array($member) ? ($member['name'] ?? null) : null,
                    $staff,
                )));

                if ($names !== []) {
                    $rows[] = ['label' => __('Responsável'), 'value' => implode(', ', $names)];
                }
            }
        }

        return $rows;
    }

    /**
     * Resolve o prefixo e a base (município) de uma viatura, memoizado por request.
     *
     * @return array{prefix: string, base: ?string}|null
     */
    private static function resolveVehicle(mixed $id): ?array
    {
        if (! is_numeric($id)) {
            return null;
        }

        $id = (int) $id;

        if (! array_key_exists($id, self::$vehicleCache)) {
            $vehicle = Vehicle::query()->withoutGlobalScopes()->with('municipio')->find($id);

            self::$vehicleCache[$id] = [
                'prefix' => (string) ($vehicle?->prefix ?: __('Viatura #:id', ['id' => $id])),
                'base' => $vehicle?->municipio?->razao_social,
            ];
        }

        return self::$vehicleCache[$id];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return list<array{label: string, value: string}>
     */
    private static function genericRows(array $payload): array
    {
        $rows = [];

        foreach ($payload as $key => $value) {
            $key = (string) $key;

            if (in_array($key, self::HIDDEN_KEYS, true) || $value === null || $value === '' || $value === []) {
                continue;
            }

            // Evita duplicar o motivo quando há uma versão já rotulada (ex.: cancelamento no local).
            if ($key === 'reason' && ! empty($payload['reason_label'])) {
                continue;
            }

            $formatted = self::formatValue($key, $value);
            if ($formatted === null || $formatted === '') {
                continue;
            }

            $rows[] = ['label' => self::label($key), 'value' => $formatted];
        }

        return $rows;
    }

    private static function formatValue(string $key, mixed $value): ?string
    {
        return match ($key) {
            'stage' => DispatchStage::tryFrom((string) $value)?->label() ?? (string) $value,
            'call_type' => CallType::tryFrom((string) $value)?->label() ?? (string) $value,
            'manchester_risk', 'priority' => ManchesterRisk::tryFrom((string) $value)?->label() ?? (string) $value,
            'modality' => IncidentReportModality::tryFrom((string) $value)?->label() ?? (string) $value,
            'decision' => RegulationDecision::tryFrom((string) $value)?->label() ?? (string) $value,
            'recommended_resource' => RegulationResource::tryFrom((string) $value)?->label() ?? (string) $value,
            'response_time_seconds' => is_numeric($value) ? gmdate('H:i:s', (int) $value) : (string) $value,
            'vehicle_id' => __('Viatura #:id', ['id' => (string) $value]),
            default => self::stringifyScalar($value),
        };
    }

    private static function stringifyScalar(mixed $value): ?string
    {
        if (is_bool($value)) {
            return $value ? __('Sim') : __('Não');
        }

        if (is_array($value)) {
            if (! array_is_list($value)) {
                return null; // objeto desconhecido: evita despejar JSON no card
            }

            $parts = array_filter(array_map(
                static fn ($item): string => is_scalar($item) ? (string) $item : '',
                $value,
            ), static fn (string $part): bool => $part !== '');

            return $parts === [] ? null : implode(', ', $parts);
        }

        return (string) $value;
    }

    private static function label(string $key): string
    {
        return match ($key) {
            'talao' => __('Talão'),
            'call_type' => __('Tipo de chamada'),
            'manchester_risk' => __('Classificação Manchester'),
            'priority' => __('Prioridade'),
            'decision' => __('Decisão'),
            'recommended_resource' => __('Recurso indicado'),
            'response_time_seconds' => __('Tempo-resposta'),
            'note' => __('Observação'),
            'support_dispatch' => __('Empenho de apoio'),
            'stage' => __('Etapa'),
            'reason', 'reason_label' => __('Motivo'),
            'modality' => __('Modalidade'),
            'items_count' => __('Itens'),
            'is_update' => __('Atualização de registro'),
            'via_nurse_report' => __('Encerrado via relatório de enfermagem'),
            'via_final_report' => __('Encerrado via relatório final'),
            'vehicle_id' => __('Viatura'),
            default => Str::of($key)->replace('_', ' ')->ucfirst()->toString(),
        };
    }

    private static function contactMethod(string $method): string
    {
        return match ($method) {
            'ramal' => __('Ramal'),
            'telefone' => __('Telefone'),
            'whatsapp' => __('WhatsApp'),
            default => $method,
        };
    }
}
