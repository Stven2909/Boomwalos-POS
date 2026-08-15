<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Integración con la API Fiscal (MH / proveedor)
    |--------------------------------------------------------------------------
    |
    | Cliente HTTP firmado con HMAC-SHA256 hacia la API fiscal.
    | La operatividad real depende de la configuración por establecimiento
    | (configuraciones_fiscales); estas claves definen el comportamiento base.
    |
    */

    'url' => env('FISCAL_API_URL'),

    'prefix' => env('FISCAL_API_PREFIX', '/api/fiscal/v1'),

    'timeout' => (int) env('FISCAL_API_TIMEOUT', 10),

    'hmac' => [
        'header' => env('FISCAL_HMAC_HEADER', 'X-Fiscal-Hmac'),
        'timestamp_header' => env('FISCAL_HMAC_TIMESTAMP_HEADER', 'X-Fiscal-Timestamp'),
        'key_header' => env('FISCAL_KEY_HEADER', 'X-Fiscal-Key'),
        'scheme' => env('FISCAL_HMAC_SCHEME', 'sha256'),
        // Tolerancia (en segundos) para la antigüedad del sello de tiempo.
        'clock_skew' => (int) env('FISCAL_CLOCK_SKEW', 300),
    ],

    'mock' => [
        // Servidor de simulación ENV-ONLY. Nunca se activa en producción.
        'enabled' => (bool) env('FISCAL_MOCK_ENABLED', false),
        // Secreto compartido que el mock exige en la firma HMAC.
        'secret' => env('FISCAL_MOCK_SECRET'),
    ],

    'documento' => [
        // La solicitud de un documento al receptor expira a las 48 h.
        'expires_hours' => (int) env('FISCAL_DOCUMENTO_EXPIRES_HOURS', 48),
    ],
];
