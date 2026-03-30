<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Laravel CORS
    |--------------------------------------------------------------------------
    |
    | allowedOrigins, allowedHeaders and allowedMethods can be set to array('*')
    | to accept any value.
    |
    */

    'paths' => ['api/*', 'sanctum/csrf-cookie', "**/print/*", ], // Se agrego print/ para mostrarse el format pdf en vendeya

    'allowed_methods' => ['*'],

    // En producción definir CORS_ALLOWED_ORIGINS=https://tudominio.com,https://admin.tudominio.com
    'allowed_origins' => explode(',', env('CORS_ALLOWED_ORIGINS', '*')),

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => false,


];
