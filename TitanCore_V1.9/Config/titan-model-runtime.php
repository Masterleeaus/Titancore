<?php

/*
|--------------------------------------------------------------------------
| Titan Core — Model Runtime Configuration
|--------------------------------------------------------------------------
|
| This file controls which AI providers are available, which is selected
| by default, and how the ProviderFailoverChain behaves.
|
| Provider keys used throughout: 'openai', 'local'
|
*/

return [

    /*
    |--------------------------------------------------------------------------
    | Default Chat Provider
    |--------------------------------------------------------------------------
    |
    | The provider key used for chat completion requests when no explicit
    | provider is supplied. Set to 'failover' to always use the chain.
    |
    */
    'default' => env('AI_PROVIDER', 'openai'),

    /*
    |--------------------------------------------------------------------------
    | Default Embedding Provider
    |--------------------------------------------------------------------------
    |
    | Falls back to 'default' if not set.
    |
    */
    'default_embedding' => env('AI_EMBEDDING_PROVIDER', env('AI_PROVIDER', 'openai')),

    /*
    |--------------------------------------------------------------------------
    | Providers
    |--------------------------------------------------------------------------
    */
    'providers' => [

        'openai' => [
            'api_key'         => env('OPENAI_API_KEY'),
            'base_url'        => env('OPENAI_BASE_URL', 'https://api.openai.com'),
            'model'           => env('OPENAI_CHAT_MODEL', 'gpt-4o-mini'),
            'embedding_model' => env('OPENAI_EMBED_MODEL', 'text-embedding-3-small'),
            'timeout_seconds' => (int) env('OPENAI_TIMEOUT', 30),
        ],

        'local' => [
            'base_url'        => env('LOCAL_MODEL_BASE_URL', 'http://localhost:11434'),
            'model'           => env('LOCAL_MODEL_DEFAULT', 'llama3'),
            'embedding_model' => env('LOCAL_MODEL_EMBED', 'nomic-embed-text'),
            'timeout_seconds' => (int) env('LOCAL_MODEL_TIMEOUT', 60),
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Failover Chain
    |--------------------------------------------------------------------------
    |
    | When enabled, gateway calls automatically fall back through the ordered
    | provider lists on error or the listed HTTP status codes.
    |
    */
    'failover' => [
        'enabled'             => (bool) env('AI_FAILOVER_ENABLED', false),
        'chat_providers'      => array_filter(array_map('trim', explode(',', env('AI_FAILOVER_CHAT_PROVIDERS', 'openai,local')))),
        'embedding_providers' => array_filter(array_map('trim', explode(',', env('AI_FAILOVER_EMBED_PROVIDERS', 'openai')))),
        'on_statuses'         => [429, 500, 502, 503, 504],
        'backoff' => [
            'base_delay_ms' => max(0, (int) env('AI_FAILOVER_BACKOFF_MS', 0)),
            'max_delay_ms'  => max(0, (int) env('AI_FAILOVER_MAX_BACKOFF_MS', 0)),
        ],
        'circuit_breaker' => [
            'failure_threshold' => max(0, (int) env('AI_FAILOVER_CIRCUIT_THRESHOLD', 0)),
            'cooldown_seconds'  => max(0, (int) env('AI_FAILOVER_CIRCUIT_COOLDOWN', 60)),
        ],
    ],

];
