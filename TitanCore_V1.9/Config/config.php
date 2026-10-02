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
        'default_panel' => 'platform_studio',
        'panel_switcher' => [
            'enabled' => true,
            'panels' => ['super_admin', 'platform_studio', 'platform_admin', 'developer'],
        ],
        'themes' => [
            'super_admin'    => 'titancore::themes.super-admin',
            'platform_studio' => 'titancore::themes.platform-studio',
            'platform_admin' => 'titancore::themes.platform-admin',
            'developer'      => 'titancore::themes.developer',
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
                    'platform_api_keys',
                ],
                'pages' => [
                    'super_admin_dashboard',
                    'platform_health',
                    'platform_settings',
                    'platform_diagnostics',
                    'platform_upgrades',
                ],
                'widgets' => [
                    'platform_health_status',
                    'platform_usage_summary',
                    'platform_alerts',
                    'platform_quick_actions',
                ],
                'navigation' => [
                    ['label' => 'Dashboard',    'page' => 'super_admin_dashboard'],
                    ['label' => 'Health',       'page' => 'platform_health'],
                    ['label' => 'Diagnostics',  'page' => 'platform_diagnostics'],
                    ['label' => 'Upgrades',     'page' => 'platform_upgrades'],
                    ['label' => 'Registry',     'resource' => 'platform_registry'],
                    ['label' => 'API Keys',     'resource' => 'platform_api_keys'],
                    ['label' => 'Settings',     'page' => 'platform_settings'],
                ],
                'global_search' => [
                    'enabled' => true,
                    'resources' => ['platform_modules', 'platform_registry'],
                ],
            ],

            'platform_studio' => [
                'id' => 'titancore-platform-studio',
                'path' => 'studio',
                'permission' => 'manage_platform',
                'dashboard' => 'platform_studio',
                'resources' => [
                    'platform_providers',
                    'platform_models',
                    'platform_api_keys',
                    'platform_modules',
                    'platform_registry',
                ],
                'pages' => [
                    'platform_studio_dashboard',
                    'platform_engine_manager',
                    'platform_runtime',
                    'platform_knowledge',
                    'platform_discovery',
                    'platform_health',
                    'platform_sdk',
                    'platform_settings',
                    'platform_upgrades',
                    'platform_diagnostics',
                    'platform_telemetry',
                ],
                'widgets' => [
                    'platform_health_status',
                    'platform_usage_summary',
                    'platform_request_volume',
                    'platform_metrics',
                    'platform_alerts',
                    'platform_recent_activity',
                    'platform_quick_actions',
                    'platform_status_indicators',
                ],
                'navigation' => [
                    ['label' => 'Dashboard',       'page' => 'platform_studio_dashboard',  'group' => null],
                    ['label' => 'Engine Manager',  'page' => 'platform_engine_manager',    'group' => 'Engines'],
                    ['label' => 'Runtime',         'page' => 'platform_runtime',           'group' => 'Engines'],
                    ['label' => 'AI Providers',    'resource' => 'platform_providers',     'group' => 'AI'],
                    ['label' => 'Models',          'resource' => 'platform_models',        'group' => 'AI'],
                    ['label' => 'Knowledge',       'page' => 'platform_knowledge',         'group' => 'AI'],
                    ['label' => 'Registries',      'resource' => 'platform_registry',      'group' => 'Platform'],
                    ['label' => 'Discovery',       'page' => 'platform_discovery',         'group' => 'Platform'],
                    ['label' => 'Platform Health', 'page' => 'platform_health',            'group' => 'Monitoring'],
                    ['label' => 'Telemetry',       'page' => 'platform_telemetry',         'group' => 'Monitoring'],
                    ['label' => 'SDK',             'page' => 'platform_sdk',               'group' => 'Developer'],
                    ['label' => 'API Keys',        'resource' => 'platform_api_keys',      'group' => 'Security'],
                    ['label' => 'Diagnostics',     'page' => 'platform_diagnostics',       'group' => 'Maintenance'],
                    ['label' => 'Upgrades',        'page' => 'platform_upgrades',          'group' => 'Maintenance'],
                    ['label' => 'Settings',        'page' => 'platform_settings',          'group' => 'Maintenance'],
                ],
                'global_search' => [
                    'enabled' => true,
                    'resources' => ['platform_providers', 'platform_models', 'platform_modules', 'platform_registry'],
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
                    'platform_models',
                ],
                'pages' => [
                    'platform_admin_dashboard',
                    'platform_settings',
                    'platform_knowledge',
                    'platform_telemetry',
                ],
                'widgets' => [
                    'platform_usage_summary',
                    'platform_request_volume',
                    'platform_metrics',
                    'platform_recent_activity',
                ],
                'navigation' => [
                    ['label' => 'Dashboard',  'page' => 'platform_admin_dashboard'],
                    ['label' => 'Providers',  'resource' => 'platform_providers'],
                    ['label' => 'Models',     'resource' => 'platform_models'],
                    ['label' => 'Knowledge',  'page' => 'platform_knowledge'],
                    ['label' => 'Telemetry',  'page' => 'platform_telemetry'],
                    ['label' => 'Usage',      'resource' => 'platform_usage'],
                    ['label' => 'Settings',   'page' => 'platform_settings'],
                ],
                'global_search' => [
                    'enabled' => true,
                    'resources' => ['platform_providers', 'platform_prompts', 'platform_models'],
                ],
            ],

            'developer' => [
                'id' => 'titancore-developer',
                'path' => 'developer',
                'permission' => 'developer_access',
                'dashboard' => 'developer',
                'resources' => [],
                'pages' => [
                    'developer_dashboard',
                    'developer_sdk_explorer',
                    'developer_manifest_validator',
                    'developer_engine_validator',
                    'developer_route_explorer',
                    'developer_event_explorer',
                    'developer_contract_explorer',
                    'developer_api_explorer',
                    'developer_generator_status',
                    'developer_architecture_validator',
                ],
                'widgets' => [
                    'platform_health_status',
                    'platform_status_indicators',
                ],
                'navigation' => [
                    ['label' => 'Dashboard',              'page' => 'developer_dashboard',              'group' => null],
                    ['label' => 'SDK Explorer',           'page' => 'developer_sdk_explorer',           'group' => 'SDK'],
                    ['label' => 'Contract Explorer',      'page' => 'developer_contract_explorer',      'group' => 'SDK'],
                    ['label' => 'Manifest Validator',     'page' => 'developer_manifest_validator',     'group' => 'Validation'],
                    ['label' => 'Engine Validator',       'page' => 'developer_engine_validator',       'group' => 'Validation'],
                    ['label' => 'Architecture Validator', 'page' => 'developer_architecture_validator', 'group' => 'Validation'],
                    ['label' => 'Route Explorer',         'page' => 'developer_route_explorer',         'group' => 'Exploration'],
                    ['label' => 'Event Explorer',         'page' => 'developer_event_explorer',         'group' => 'Exploration'],
                    ['label' => 'API Explorer',           'page' => 'developer_api_explorer',           'group' => 'Exploration'],
                    ['label' => 'Generator Status',       'page' => 'developer_generator_status',       'group' => 'Tools'],
                ],
                'global_search' => [
                    'enabled' => false,
                    'resources' => [],
                ],
            ],
        ],

        'settings_framework' => [
            'groups' => [
                ['key' => 'general',     'label' => 'General',      'permission' => 'manage_platform',     'icon' => 'cog'],
                ['key' => 'ai',          'label' => 'AI',           'permission' => 'manage_ai',           'icon' => 'sparkles'],
                ['key' => 'runtime',     'label' => 'Runtime',      'permission' => 'manage_platform',     'icon' => 'server'],
                ['key' => 'providers',   'label' => 'Providers',    'permission' => 'manage_ai',           'icon' => 'cloud'],
                ['key' => 'knowledge',   'label' => 'Knowledge',    'permission' => 'manage_ai_kb',        'icon' => 'book-open'],
                ['key' => 'storage',     'label' => 'Storage',      'permission' => 'manage_platform',     'icon' => 'database'],
                ['key' => 'telemetry',   'label' => 'Telemetry',    'permission' => 'manage_platform',     'icon' => 'chart-bar'],
                ['key' => 'security',    'label' => 'Security',     'permission' => 'manage_security',     'icon' => 'shield-check'],
                ['key' => 'workspace',   'label' => 'Workspace',    'permission' => 'manage_platform',     'icon' => 'office-building'],
                ['key' => 'marketplace', 'label' => 'Marketplace',  'permission' => 'manage_marketplace',  'icon' => 'shopping-bag'],
                ['key' => 'developer',   'label' => 'Developer',    'permission' => 'developer_access',    'icon' => 'code'],
                ['key' => 'prompts',     'label' => 'Prompts',      'permission' => 'manage_ai_prompts',   'icon' => 'chat'],
                ['key' => 'policies',    'label' => 'Policies',     'permission' => 'manage_ai',           'icon' => 'clipboard-list'],
            ],
        ],

        'dashboard_framework' => [
            'widget_types' => [
                'card'              => ['label' => 'Card',              'resizable' => false],
                'metric'            => ['label' => 'Metric',            'resizable' => false],
                'chart'             => ['label' => 'Chart',             'resizable' => true],
                'health'            => ['label' => 'Health',            'resizable' => false],
                'alert'             => ['label' => 'Alert',             'resizable' => false],
                'recent_activity'   => ['label' => 'Recent Activity',   'resizable' => true],
                'quick_actions'     => ['label' => 'Quick Actions',     'resizable' => false],
                'status_indicators' => ['label' => 'Status Indicators', 'resizable' => false],
            ],
            'dashboards' => [
                'super_admin' => [
                    'widgets' => [
                        'platform_health_status',
                        'platform_usage_summary',
                        'platform_alerts',
                        'platform_quick_actions',
                    ],
                ],
                'platform_studio' => [
                    'widgets' => [
                        'platform_health_status',
                        'platform_usage_summary',
                        'platform_request_volume',
                        'platform_metrics',
                        'platform_alerts',
                        'platform_recent_activity',
                        'platform_quick_actions',
                        'platform_status_indicators',
                    ],
                ],
                'platform_admin' => [
                    'widgets' => [
                        'platform_usage_summary',
                        'platform_request_volume',
                        'platform_metrics',
                        'platform_recent_activity',
                    ],
                ],
                'developer' => [
                    'widgets' => [
                        'platform_health_status',
                        'platform_status_indicators',
                    ],
                ],
            ],
        ],

        'diagnostics' => [
            'domains' => [
                ['key' => 'runtime',   'label' => 'Runtime Diagnostics',   'permission' => 'manage_platform'],
                ['key' => 'provider',  'label' => 'Provider Diagnostics',  'permission' => 'manage_ai'],
                ['key' => 'knowledge', 'label' => 'Knowledge Diagnostics', 'permission' => 'manage_ai_kb'],
                ['key' => 'engine',    'label' => 'Engine Diagnostics',    'permission' => 'manage_platform'],
                ['key' => 'sdk',       'label' => 'SDK Diagnostics',       'permission' => 'developer_access'],
                ['key' => 'registry',  'label' => 'Registry Diagnostics',  'permission' => 'manage_platform'],
            ],
        ],

        'health_centre' => [
            'domains' => [
                ['key' => 'runtime',   'label' => 'Runtime',   'permission' => 'manage_platform'],
                ['key' => 'engine',    'label' => 'Engine',    'permission' => 'manage_platform'],
                ['key' => 'queue',     'label' => 'Queue',     'permission' => 'manage_platform'],
                ['key' => 'knowledge', 'label' => 'Knowledge', 'permission' => 'manage_ai_kb'],
                ['key' => 'discovery', 'label' => 'Discovery', 'permission' => 'manage_platform'],
                ['key' => 'registry',  'label' => 'Registry',  'permission' => 'manage_platform'],
                ['key' => 'provider',  'label' => 'Provider',  'permission' => 'manage_ai'],
                ['key' => 'sdk',       'label' => 'SDK',       'permission' => 'developer_access'],
            ],
        ],

        'upgrade_centre' => [
            'features' => [
                'available_updates'      => true,
                'installed_versions'     => true,
                'upgrade_history'        => true,
                'rollback'               => true,
                'compatibility_checks'   => true,
                'preflight_validation'   => true,
                'post_upgrade_validation' => true,
            ],
        ],
    ],
];
