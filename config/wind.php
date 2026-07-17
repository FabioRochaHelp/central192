<?php

declare(strict_types=1);

/**
 * Vento em superfície (10 m) exibido como camada do mapa operacional.
 *
 * Provedor padrão: Open-Meteo (https://open-meteo.com) — API pública, sem chave.
 * Uso gratuito é restrito a fins não-comerciais e exige atribuição (CC-BY 4.0);
 * confira os termos vigentes antes de usar em contexto comercial.
 */
return [
    'base_url' => rtrim((string) env('WIND_BASE_URL', 'https://api.open-meteo.com'), '/'),

    /** Endpoint (relativo à base_url) que devolve as condições atuais. */
    'forecast_path' => (string) env('WIND_FORECAST_PATH', 'v1/forecast'),

    /** Timeout curto: vento é contextual, não pode segurar o carregamento do mapa. */
    'timeout' => (int) env('WIND_TIMEOUT', 8),

    /** O Open-Meteo atualiza o bloco `current` a cada 15 min (interval: 900). */
    'cache_ttl_minutes' => (int) env('WIND_CACHE_TTL_MINUTES', 15),

    'grid' => [
        /**
         * Resoluções (em graus) tentadas em ordem: usamos a mais fina que couber
         * no teto de células. 0.25° ≈ resolução nativa do modelo — pedir mais fino
         * que isso gera pontos diferentes com o mesmo valor.
         */
        'steps' => [0.25, 0.5, 1.0, 2.0],

        /** Teto de pontos por requisição — limita o tamanho da chamada ao provedor. */
        'max_cells' => (int) env('WIND_MAX_CELLS', 24),
    ],
];
