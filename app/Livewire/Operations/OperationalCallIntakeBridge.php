<?php

declare(strict_types=1);

namespace App\Livewire\Operations;

use App\Support\Operations\IncidentPhoneNormalizer;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * Ouve `operational-call-intake` (Echo/Reverb → Livewire.dispatch) em qualquer tela do layout operacional,
 * não só na Central — evita modal silencioso quando o operador não está em `/operations/dispatch`.
 */
final class OperationalCallIntakeBridge extends Component
{
    public bool $showCallIntakeModal = false;

    /** @var array<string, mixed> */
    public array $callIntakePrefill = [];

    public int $callIntakeRenderKey = 0;

    public function closeCallIntakeModal(): void
    {
        $this->showCallIntakeModal = false;
        $this->callIntakePrefill = [];
        session()->forget('operations.call_intake_modal_prefill');
    }

    #[On('operational-call-intake')]
    public function openOperationalCallIntakeFromBroadcast(
        mixed $form_url = null,
        mixed $phone = null,
        mixed $expires_at = null,
        mixed $caller_name = null,
        mixed $latitude = null,
        mixed $longitude = null,
        mixed $call_received_at = null,
        mixed $external_reference = null,
        mixed $uniqueid = null,
        mixed $alert_id = null,
        mixed $location_key = null,
        mixed $socket = null,
    ): void {
        $user = Auth::user();
        if ($user === null || ! $user->hasOperationalAbility('incident.create')) {
            return;
        }

        session()->forget('operations.call_intake_modal_prefill');

        $this->callIntakePrefill = [
            'alert_id' => self::nullableTrimmedString($alert_id),
            'location_key' => self::nullableTrimmedString($location_key ?? null),
            'form_url' => self::stringFromMixed($form_url),
            'phone' => self::incomingPhoneDigits($phone),
            'expires_at' => self::stringFromMixed($expires_at),
            'caller_name' => self::nullableTrimmedString($caller_name),
            'latitude' => self::coordToPrefillScalar($latitude),
            'longitude' => self::coordToPrefillScalar($longitude),
            'call_received_at' => self::nullableTrimmedString($call_received_at),
            'external_reference' => self::nullableTrimmedString($external_reference),
            'uniqueid' => self::nullableTrimmedString($uniqueid),
        ];
        session()->put('operations.call_intake_modal_prefill', $this->callIntakePrefill);

        unset($socket);

        $this->callIntakeRenderKey++;
        $this->showCallIntakeModal = true;
    }

    #[On('call-intake-incident-saved')]
    public function onCallIntakeIncidentSaved(int $incidentId): void
    {
        unset($incidentId);
        $this->closeCallIntakeModal();
    }

    /** Ligação somada como solicitação de ocorrência existente: fecha o modal do mesmo jeito. */
    #[On('call-intake-request-attached')]
    public function onCallIntakeRequestAttached(int $incidentId): void
    {
        unset($incidentId);
        $this->closeCallIntakeModal();
    }

    public function render(): View
    {
        return view('livewire.operations.operational-call-intake-bridge');
    }

    private static function stringFromMixed(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        return is_string($value) ? $value : (string) $value;
    }

    private static function nullableTrimmedString(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        $s = trim(is_string($value) ? $value : (string) $value);

        return $s === '' ? null : $s;
    }

    /** Echo pode mandar `phone` como número; com strict_types, `?string` quebrava o listener inteiro. */
    private static function incomingPhoneDigits(mixed $phone): string
    {
        if ($phone === null || $phone === '') {
            return '';
        }
        if (is_string($phone)) {
            return IncidentPhoneNormalizer::normalize($phone);
        }
        if (is_int($phone)) {
            return IncidentPhoneNormalizer::normalize((string) $phone);
        }
        if (is_float($phone)) {
            return IncidentPhoneNormalizer::normalize(sprintf('%.0f', $phone));
        }

        return '';
    }

    /** Echo/Reverb pode entregar latitude/longitude como número no JSON. */
    private static function coordToPrefillScalar(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }
        if (is_string($value)) {
            $t = trim($value);

            return $t === '' ? null : $t;
        }
        if (is_int($value) || is_float($value)) {
            return (string) $value;
        }

        return null;
    }
}
