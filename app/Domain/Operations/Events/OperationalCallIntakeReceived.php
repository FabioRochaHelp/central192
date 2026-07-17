<?php

declare(strict_types=1);

namespace App\Domain\Operations\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Disparado quando o webhook de entrada de chamada (PBX) gera uma URL de formulário.
 * Clientes Echo (Reverb) no canal privado `operations.call-intake` recebem o payload e disparam
 * o evento Livewire `operational-call-intake`, que abre o modal de cadastro na Central.
 * Equivale ao fluxo legado Socket.IO `new-call`.
 */
final class OperationalCallIntakeReceived implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets;

    public function __construct(
        public string $formUrl,
        public string $phoneDigits,
        public string $expiresAtIso,
        public ?string $callerName = null,
        public ?string $latitude = null,
        public ?string $longitude = null,
        public ?string $callReceivedAtIso = null,
        public ?string $externalReference = null,
        public ?string $uniqueid = null,
    ) {}

    /** @return array<int, PrivateChannel> */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('operations.call-intake'),
        ];
    }

    public function broadcastAs(): string
    {
        return 'operational.call-intake';
    }

    /** @return array<string, string> */
    public function broadcastWith(): array
    {
        return [
            'form_url' => $this->formUrl,
            'phone' => $this->phoneDigits,
            'expires_at' => $this->expiresAtIso,
            'caller_name' => $this->callerName,
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
            'call_received_at' => $this->callReceivedAtIso,
            'external_reference' => $this->externalReference,
            'uniqueid' => $this->uniqueid,
        ];
    }
}
