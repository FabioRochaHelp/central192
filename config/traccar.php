<?php

declare(strict_types=1);

$baseUrl = rtrim((string) env('TRACCAR_BASE_URL', env('TRACCAR_URL', '')), '/');

return [
    'base_url' => $baseUrl,

    /** @deprecated Use base_url — kept for backward compatibility */
    'url' => $baseUrl,

    'auth_type' => env('TRACCAR_AUTH_TYPE', 'basic'),

    'username' => (string) env('TRACCAR_USERNAME', env('TRACCAR_EMAIL', '')),

    /** @deprecated Use username */
    'email' => (string) env('TRACCAR_USERNAME', env('TRACCAR_EMAIL', '')),

    'password' => (string) env('TRACCAR_PASSWORD', ''),

    'api_key' => (string) env('TRACCAR_API_KEY', ''),

    'timeout' => (int) env('TRACCAR_TIMEOUT', 10),

    /** Relatórios de rota podem demorar mais que posições ao vivo. */
    'report_timeout' => (int) env('TRACCAR_REPORT_TIMEOUT', 30),

    'verify_ssl' => filter_var(env('TRACCAR_VERIFY_SSL', true), FILTER_VALIDATE_BOOL),

    'session_email' => (string) env('TRACCAR_SESSION_EMAIL', env('TRACCAR_USERNAME', env('TRACCAR_EMAIL', ''))),

    'session_password' => (string) env('TRACCAR_SESSION_PASSWORD', env('TRACCAR_PASSWORD', '')),

    'session_cache_minutes' => (int) env('TRACCAR_SESSION_CACHE_MINUTES', 25),

    'positions_sync_interval' => (int) env('TRACCAR_POSITIONS_SYNC_INTERVAL', 60),

    /** Sem comunicação após este intervalo, o rastreador é considerado offline. */
    'device_online_threshold_seconds' => (int) env('TRACCAR_DEVICE_ONLINE_THRESHOLD_SECONDS', 180),

    'circuit_breaker_threshold' => (int) env('TRACCAR_CIRCUIT_BREAKER_THRESHOLD', 3),

    'circuit_breaker_cooldown_minutes' => (int) env('TRACCAR_CIRCUIT_BREAKER_COOLDOWN_MINUTES', 5),
];
