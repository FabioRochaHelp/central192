<?php

declare(strict_types=1);

return [
    /**
     * Provedor de rastreamento ativo. Seleciona o adapter que implementa
     * App\Integrations\Tracking\Contracts\TrackingProvider.
     *
     * Suportados: 'traccar' (padrão) e 'ssx' (SystemSatX).
     */
    'provider' => env('TRACKING_PROVIDER', 'traccar'),
];
