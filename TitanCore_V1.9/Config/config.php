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

    /*
    |--------------------------------------------------------------------------
    | Filament Platform Administration Framework
    |--------------------------------------------------------------------------
    */
    'filament' => [
        'default_panel' => 'platform_admin',
        'panel_switcher' => [
            'enabled' => true,
            'panels' => ['super_admin', 'platform_admin'],
        ],
        'themes' => [
            'super_admin' => 'titancore::themes.super-admin',
            'platform_admin' => 'titancore::themes.platform-admin',
        ],
        'panels' => [
            'super_admin' => [
                'id' => 'titancore-super-admin',
                'path' => 'admin/titancore',
                'permission' => 'super-admin',
                'dashboard' => 'super_admin',
                'resources' => [
                    'platform_modules',
                    'platform_registry',
                    'platform_providers',
                ],
                'pages' => [
                    'super_admin_dashboard',
                    'platform_health',
                    'platform_settings',
                ],
                'widgets' => [
                    'platform_health_status',
                    'platform_usage_summary',
                ],
                'navigation' => [
                    ['label' => 'Dashboard', 'page' => 'super_admin_dashboard'],
                    ['label' => 'Registry', 'resource' => 'platform_registry'],
                    ['label' => 'Settings', 'page' => 'platform_settings'],
                ],
                'global_search' => [
                    'enabled' => true,
                    'resources' => ['platform_modules', 'platform_registry'],
                ],
            ],
            'platform_admin' => [
                'id' => 'titancore-platform-admin',
                'path' => 'platform/titancore',
                'permission' => 'manage_ai',
                'dashboard' => 'platform_admin',
                'resources' => [
                    'platform_providers',
                    'platform_prompts',
                    'platform_usage',
                ],
                'pages' => [
                    'platform_admin_dashboard',
                    'platform_settings',
                ],
                'widgets' => [
                    'platform_usage_summary',
                    'platform_request_volume',
                ],
                'navigation' => [
                    ['label' => 'Dashboard', 'page' => 'platform_admin_dashboard'],
                    ['label' => 'Providers', 'resource' => 'platform_providers'],
                    ['label' => 'Usage', 'resource' => 'platform_usage'],
                    ['label' => 'Settings', 'page' => 'platform_settings'],
                ],
                'global_search' => [
                    'enabled' => true,
                    'resources' => ['platform_providers', 'platform_prompts'],
                ],
            ],
        ],
        'settings_framework' => [
            'groups' => [
                ['key' => 'providers', 'permission' => 'manage_ai'],
                ['key' => 'policies', 'permission' => 'manage_ai'],
                ['key' => 'knowledge', 'permission' => 'manage_ai_kb'],
                ['key' => 'prompts', 'permission' => 'manage_ai_prompts'],
            ],
        ],
        'dashboard_framework' => [
            'dashboards' => [
                'super_admin' => [
                    'widgets' => ['platform_health_status', 'platform_usage_summary'],
                ],
                'platform_admin' => [
                    'widgets' => ['platform_usage_summary', 'platform_request_volume'],
                ],
            ],
        ],
    ],
];
