<?php

declare(strict_types=1);

return [
    /** Base da API de integração SSX/SystemSatX (sem barra final). */
    'base_url' => rtrim((string) env('SSX_BASE_URL', 'https://integration.systemsatx.com.br'), '/'),

    'username' => (string) env('SSX_USERNAME', ''),

    'password' => (string) env('SSX_PASSWORD', ''),

    /** Autenticação por hash (alternativa a usuário/senha). Opcional. */
    'hash_central' => (string) env('SSX_HASH_CENTRAL', ''),

    'hash_auth' => (string) env('SSX_HASH_AUTH', ''),

    /** Código de integração do cliente (contexto GlobalBus). Opcional. */
    'client_integration_code_bus' => (string) env('SSX_CLIENT_INTEGRATION_CODE_BUS', ''),

    'timeout' => (int) env('SSX_TIMEOUT', 15),

    'verify_ssl' => filter_var(env('SSX_VERIFY_SSL', true), FILTER_VALIDATE_BOOL),

    /** TTL do token quando o Login não devolve ExpiresIn (minutos). */
    'token_cache_minutes' => (int) env('SSX_TOKEN_CACHE_MINUTES', 25),

    /** Sem posição mais nova que isto, o rastreador é considerado offline (segundos). */
    'device_online_threshold_seconds' => (int) env('SSX_DEVICE_ONLINE_THRESHOLD_SECONDS', 180),

    /** Formato de data enviado nos filtros (EventDate). */
    'date_format' => (string) env('SSX_DATE_FORMAT', 'Y-m-d\TH:i:s'),

    'positions_sync_interval' => (int) env('SSX_POSITIONS_SYNC_INTERVAL', 60),

    'circuit_breaker_threshold' => (int) env('SSX_CIRCUIT_BREAKER_THRESHOLD', 3),

    'circuit_breaker_cooldown_minutes' => (int) env('SSX_CIRCUIT_BREAKER_COOLDOWN_MINUTES', 5),
];
