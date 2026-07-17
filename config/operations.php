<?php

declare(strict_types=1);

return [
    /**
     * Segredo para o webhook `POST /integrations/calls/incident-intake`.
     * Envio obrigatório no cabeçalho HTTP `X-Webhook-Secret`.
     */
    'call_webhook_secret' => (string) env('OPERATIONS_CALL_WEBHOOK_SECRET', ''),

    /** Tempo de vida da URL assinada retornada pelo webhook (minutos). */
    'call_intake_signed_url_ttl_minutes' => max(5, min(120, (int) env('OPERATIONS_CALL_INTAKE_URL_TTL_MINUTES', 30))),

    /** Tempo de vida dos alertas no mapa tático (dias). */
    'call_alert_ttl_days' => max(1, min(30, (int) env('OPERATIONS_CALL_ALERT_TTL_DAYS', 3))),

    /**
     * Envia evento WebSocket (Reverb) para o canal `operations.dispatch`, abrindo o formulário nos navegadores conectados.
     * Requer BROADCAST_CONNECTION=reverb (ou compatível) e `php artisan reverb:start`.
     */
    'broadcast_call_intake' => filter_var(env('OPERATIONS_BROADCAST_CALL_INTAKE', true), FILTER_VALIDATE_BOOL),

    /** Raio (metros) para avisar o operador de ocorrência já registrada no mesmo ponto. */
    'duplicate_incident_radius_meters' => max(50, min(2000, (int) env('OPERATIONS_DUPLICATE_INCIDENT_RADIUS_METERS', 200))),

    /** Janela (horas) considerada na busca por ocorrência próxima ainda ativa. */
    'duplicate_incident_window_hours' => max(1, min(72, (int) env('OPERATIONS_DUPLICATE_INCIDENT_WINDOW_HOURS', 24))),

    /** User-Agent HTTP nas requisições Nominatim (política OSM). Opcional. */
    'osm_nominatim_user_agent' => env('OPERATIONS_OSM_NOMINATIM_USER_AGENT', ''),

    /**
     * Integração com o PABX (Asterisk) para reprodução de gravações de chamadas.
     * A URL do áudio é montada como `{base}/recordings/{uniqueid}/play` e consumida
     * exclusivamente pelo servidor (proxy), nunca exposta ao navegador.
     */
    'pabx_recording_base_url' => rtrim((string) env('OPERATIONS_PABX_BASE_URL', 'https://pabx.rocksky.com.br/api/v1'), '/'),

    /** Bearer token da API do PABX. Fica só no servidor. */
    'pabx_recording_token' => (string) env('OPERATIONS_PABX_TOKEN', ''),
];
