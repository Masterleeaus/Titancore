<?php

return [
    /*
    |--------------------------------------------------------------------------
    | TitanCore - Titan AI Provider Configuration
    |--------------------------------------------------------------------------
    |
    | TitanCore can route tool invocations to Titan AI SaaS as the backend engine.
    | Authentication is HTTP Basic Auth: username=API key, password=empty.
    |
    */
    'providers' => [
        'titanai' => [
            'enabled'  => env('TITAN_titanai_ENABLED', true),
            'base_url' => rtrim(env('TITAN_titanai_BASE_URL', 'https://your-titanai-domain.tld'), '/'),
            'api_key'  => env('TITAN_titanai_API_KEY', ''),
            // Optional: restrict proxy paths for safety
            'allowed_path_prefixes' => [
                '/api', '/v1', '/v2'
            ],
            'timeout_seconds' => (int) env('TITAN_titanai_TIMEOUT', 60),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Logging / Audit
    |--------------------------------------------------------------------------
    */
    'audit' => [
        'enabled' => true,
    ],
];
