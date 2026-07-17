<?php

declare(strict_types=1);

return [
    // Área geográfica padrão dos focos de calor — bounding box do estado de SP.
    // Como a maioria dos focos vem sem UF preenchida, filtramos por coordenada
    // (não pelo rótulo `estado`) para não perder registros da região.
    'focos_bbox' => [
        'min_lat' => (float) env('FIRE_FOCOS_MIN_LAT', -25.5),
        'max_lat' => (float) env('FIRE_FOCOS_MAX_LAT', -19.7),
        'min_lon' => (float) env('FIRE_FOCOS_MIN_LON', -53.2),
        'max_lon' => (float) env('FIRE_FOCOS_MAX_LON', -44.0),
        // UF do consórcio: focos rotulados com outra UF (que vazam na borda do retângulo)
        // são descartados; os sem UF (NULL) dentro da bbox são mantidos.
        'state_label' => env('FIRE_FOCOS_STATE_LABEL', 'SP'),
    ],

    'dashboard_map_days' => (int) env('FIRE_DASHBOARD_MAP_DAYS', 60),
    'dashboard_map_limit' => (int) env('FIRE_DASHBOARD_MAP_LIMIT', 500),
    'import_batch_incremental' => (int) env('FIRE_IMPORT_BATCH_INCREMENTAL', 100),
    'import_batch_bulk' => (int) env('FIRE_IMPORT_BATCH_BULK', 1000),
];
