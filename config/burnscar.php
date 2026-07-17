<?php

declare(strict_types=1);

/**
 * Serviço externo de análise de cicatriz de incêndio (burn scar).
 *
 * O Laravel envia um JSON de requisição (localização/período/fonte de satélite) e
 * recebe de volta a cicatriz calculada (índices, severidade, área queimada, GeoJSON),
 * conforme o esquema em .agents/skills/wildfire-scar-analysis/SKILL.md.
 */
return [
    /**
     * Provedor da análise de cicatriz:
     *  - 'external' → serviço externo de sensoriamento remoto (BURNSCAR_BASE_URL)
     *  - 'focos'    → estimativa local a partir dos focos de calor (INPE) já importados
     */
    'provider' => env('BURNSCAR_PROVIDER', 'external'),

    /** Parâmetros do estimador local por focos de calor. */
    'focos' => [
        // Raio (km) ao redor da coordenada para coletar focos.
        'radius_km' => (float) env('BURNSCAR_FOCOS_RADIUS_KM', 15),
        // Janela (dias) ao redor da data pós-fogo quando não há intervalo pré/pós definido.
        'window_days' => (int) env('BURNSCAR_FOCOS_WINDOW_DAYS', 10),
        // Buffer (m) aplicado a cada foco ao montar o polígono da cicatriz (~footprint do pixel).
        'buffer_m' => (float) env('BURNSCAR_FOCOS_BUFFER_M', 500),
    ],

    'base_url' => rtrim((string) env('BURNSCAR_BASE_URL', ''), '/'),

    /** Endpoint (relativo à base_url) que recebe o POST da análise. */
    'analyze_path' => (string) env('BURNSCAR_ANALYZE_PATH', 'analyze'),

    'token' => (string) env('BURNSCAR_TOKEN', ''),

    'timeout' => (int) env('BURNSCAR_TIMEOUT', 60),

    'verify_ssl' => filter_var(env('BURNSCAR_VERIFY_SSL', true), FILTER_VALIDATE_BOOL),

    'circuit_breaker_threshold' => (int) env('BURNSCAR_CIRCUIT_BREAKER_THRESHOLD', 3),

    'circuit_breaker_cooldown_minutes' => (int) env('BURNSCAR_CIRCUIT_BREAKER_COOLDOWN_MINUTES', 5),
];
