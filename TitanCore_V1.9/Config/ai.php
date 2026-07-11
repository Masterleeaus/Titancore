<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default AI Provider
    |--------------------------------------------------------------------------
    |
    | This is the provider key from the 'providers' array below that Titan Core
    | will use by default when no specific provider is requested.
    |
    */

    'default' => env('AI_PROVIDER', 'openai'),

    /*
    |--------------------------------------------------------------------------
    | Providers
    |--------------------------------------------------------------------------
    |
    | Configure the different AI providers your app can use.
    |
    */

    'providers' => [

        'openai' => [
            'api_key' => env('OPENAI_API_KEY'),
            'base'    => env('OPENAI_BASE', 'https://api.openai.com'),
            'model'   => env('OPENAI_MODEL', 'gpt-4o-mini'),
        ],

        'anthropic' => [
            'api_key' => env('ANTHROPIC_API_KEY'),
            'base'    => env('ANTHROPIC_BASE', 'https://api.anthropic.com'),
            'model'   => env('ANTHROPIC_MODEL', 'claude-3-haiku'),
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Feature Toggles
    |--------------------------------------------------------------------------
    */

    

    /*
    |--------------------------------------------------------------------------
    | Pricing (USD per 1K tokens)
    |--------------------------------------------------------------------------
    | Keep these in env/config so you can update without code changes.
    | Unknown models default to null cost (still logs tokens).
    |
    */
    'pricing' => [
        // Example:
        // 'gpt-4o-mini' => ['prompt_per_1k' => 0.0, 'completion_per_1k' => 0.0],
        // 'text-embedding-3-small' => ['embedding_per_1k' => 0.0],
    ],

    'features' => [
        'chat'       => true,
        'embeddings' => true,
        'vision'     => false,
        'tts'        => false,
    ],

];
