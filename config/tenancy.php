<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Tenant storage mode
    |--------------------------------------------------------------------------
    |
    | `single` keeps the current database for local development and tests.
    | Production must use `database`, where the platform registry remains in
    | the platform connection and each company uses the dynamic `tenant`
    | connection.
    |
    */
    'mode' => env('TENANT_DATABASE_MODE', 'single'),

    'fallback_connection' => env('TENANT_FALLBACK_CONNECTION', env('DB_CONNECTION', 'sqlite')),

    'default_slug' => env('TENANT_DEFAULT_SLUG', 'demo'),

    'base_domain' => env('TENANT_BASE_DOMAIN', 'pos.localhost'),

    'require_explicit_establishment' => env(
        'TENANT_REQUIRE_EXPLICIT_ESTABLISHMENT',
        env('TENANT_DATABASE_MODE', 'single') === 'database',
    ),

];
