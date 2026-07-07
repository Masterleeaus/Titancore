<?php

namespace Modules\TitanCore\Http\Controllers\Diag;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

/**
 * TitanCore Diagnostic Suite
 * Read-only endpoints for Super Admins to inspect module wiring.
 */
class DiagController extends Controller
{
    protected function moduleMeta(): array
    {
        $basePath = dirname(__DIR__, 3); // Modules/TitanCore
        $version = null;
        $moduleJson = [];
        $composer = [];

        try {
            $vFile = $basePath . DIRECTORY_SEPARATOR . 'version.txt';
            if (is_file($vFile)) {
                $version = trim((string) file_get_contents($vFile));
            }
        } catch (\Throwable $e) {}

        try {
            $mFile = $basePath . DIRECTORY_SEPARATOR . 'module.json';
            if (is_file($mFile)) {
                $moduleJson = json_decode((string) file_get_contents($mFile), true) ?: [];
            }
        } catch (\Throwable $e) {}

        try {
            $cFile = $basePath . DIRECTORY_SEPARATOR . 'composer.json';
            if (is_file($cFile)) {
                $composer = json_decode((string) file_get_contents($cFile), true) ?: [];
            }
        } catch (\Throwable $e) {}

        return [
            'name' => Arr::get($moduleJson, 'name', 'TitanCore'),
            'alias' => Arr::get($moduleJson, 'alias', 'titancore'),
            'version' => $version,
            'composer' => [
                'name' => Arr::get($composer, 'name'),
                'description' => Arr::get($composer, 'description'),
            ],
        ];
    }

    public function overview(Request $request)
    {
        $meta = $this->moduleMeta();

        return response()->json([
            'module' => $meta['name'],
            'alias' => $meta['alias'],
            'version' => $meta['version'],
            'environment' => app()->environment(),
            'app' => [
                'php' => PHP_VERSION,
                'laravel' => app()->version(),
                'debug' => (bool) config('app.debug'),
                'config_cached' => app()->configurationIsCached(),
                'routes_cached' => app()->routesAreCached(),
            ],
            'timestamp' => now()->toIso8601String(),
            'status' => 'ok',
        ]);
    }

    public function routes()
    {
        $all = Route::getRoutes();
        $out = [
            'ui' => [
                'superadmin' => [],
                'tenant' => [],
            ],
            'api' => [],
            'redirects' => [],
            'other' => [],
        ];

        foreach ($all as $r) {
            $uri = '/' . ltrim($r->uri(), '/');
            $name = $r->getName();
            $methods = implode('|', $r->methods());
            $mw = $r->gatherMiddleware();

            $row = [
                'methods' => $methods,
                'uri' => $uri,
                'name' => $name,
                'middleware' => array_values(array_unique($mw)),
            ];

            // Categorize TitanCore-related routes
            if (Str::contains($uri, 'account/settings/titancore')) {
                $out['ui']['superadmin'][] = $row;
            } elseif (Str::contains($uri, 'account/titancore')) {
                $out['ui']['tenant'][] = $row;
            } elseif (Str::startsWith($uri, '/api/titancore')) {
                $out['api'][] = $row;
            } elseif (Str::contains($uri, 'titancore') || Str::startsWith((string) $name, 'titancore.')) {
                // Heuristic for redirects: if route action is redirect or uri is admin/settings/titancore
                if (Str::contains($uri, '/admin/settings/titancore') || Str::contains($uri, '/titancore/')) {
                    $out['redirects'][] = $row;
                } else {
                    $out['other'][] = $row;
                }
            }
        }

        // Sort for readability
        foreach (['superadmin','tenant'] as $k) {
            usort($out['ui'][$k], fn($a,$b) => strcmp($a['uri'], $b['uri']));
        }
        usort($out['api'], fn($a,$b) => strcmp($a['uri'], $b['uri']));
        usort($out['redirects'], fn($a,$b) => strcmp($a['uri'], $b['uri']));
        usort($out['other'], fn($a,$b) => strcmp($a['uri'], $b['uri']));

        return response()->json($out);
    }

    public function views()
    {
        $checks = [
            'titancore::super-admin.magicai.console',
            'titancore::admin.dashboard.index',
            'titancore::admin.usage.index',
            'titancore::admin.prompts.index',
            'titancore::admin.settings.index',
            'titancore::tenant.magicai.launcher',
            // host layout pieces
            'super-admin.sections.topbar',
            'super-admin.sections.super-admin-menu',
            'sections.sidebar',
            'layouts.app',
        ];

        $exists = [];
        foreach ($checks as $v) {
            try {
                $exists[$v] = view()->exists($v);
            } catch (\Throwable $e) {
                $exists[$v] = false;
            }
        }

        // Layout contract variables (best-effort; these are injected by view composers in TitanCore v7+)
        $contract = [
            'pageTitle' => 'required',
            'checkListCompleted' => 'required',
            'checkListTotal' => 'required',
            'unreadNotificationCount' => 'required',
            'unreadNotifications' => 'required',
            'sidebarSuperadminPermissions' => 'required',
        ];

        return response()->json([
            'views' => $exists,
            'layout_contract' => $contract,
        ]);
    }

    public function permissions()
    {
        $defined = [];
        try { $defined = array_keys((array) config('titancore.permissions', [])); } catch (\Throwable $e) {}

        // User permission checks - supports common helper patterns
        $user = null;
        try { $user = function_exists('user') ? user() : auth()->user(); } catch (\Throwable $e) {}

        $userHas = [];
        $probe = ['manage_ai', 'manage_ai_prompts', 'manage_ai_kb', 'view_ai_usage', 'use_ai_features', 'view_titancore'];
        foreach ($probe as $p) {
            $userHas[$p] = null;
            try {
                if ($user) {
                    if (method_exists($user, 'permission')) {
                        $userHas[$p] = (bool) $user->permission($p);
                    } elseif (method_exists($user, 'can')) {
                        $userHas[$p] = (bool) $user->can($p);
                    }
                }
            } catch (\Throwable $e) {
                $userHas[$p] = null;
            }
        }

        return response()->json([
            'defined_permissions' => $defined,
            'user' => [
                'id' => $user->id ?? null,
                'email' => $user->email ?? null,
            ],
            'user_has' => $userHas,
            'missing_for_sidebar' => in_array('view_titancore', $defined) ? [] : ['view_titancore'],
        ]);
    }

    public function config()
    {
        $magicai = [
            'enabled' => (bool) config('titancore.magicai.enabled', true),
            'base_url_set' => (bool) config('titancore.magicai.base_url'),
            'timeout' => (int) config('titancore.magicai.timeout', 60),
            // never return secrets
            'api_key_set' => (bool) config('titancore.magicai.api_key'),
        ];

        $limits = [
            'daily_token_limit' => (int) config('titancore.daily_token_limit', 200000),
        ];

        $policies = [
            'default_allow' => (array) config('titancore.policies.default_allow', []),
        ];

        return response()->json([
            'magicai' => $magicai,
            'limits' => $limits,
            'policies' => $policies,
        ]);
    }

    public function sidebar()
    {
        // TitanCore does not register sidebar entries by default; it is Settings-only unless promoted.
        $registered = false;

        // Heuristic: detect whether a sidebar permission exists
        $defined = array_keys((array) config('titancore.permissions', []));
        $hasKey = in_array('view_titancore', $defined);

        $reasons = [];
        if (!$registered) $reasons[] = 'No SuperAdmin sidebar menu entry registered for TitanCore.';
        if (!$hasKey) $reasons[] = 'No sidebar permission key defined (suggest: view_titancore).';

        return response()->json([
            'sidebar_registered' => $registered,
            'permission_key_present' => $hasKey,
            'reason' => $reasons,
            'suggested_fix' => 'Register a SuperAdmin sidebar menu entry and gate with view_titancore (or manage_ai) permission.',
        ]);
    }

    public function recommendations()
    {
        $recs = [];

        // Core observations
        $recs[] = 'TitanCore is currently mounted as Settings-only under /account/settings/titancore/*.';
        $recs[] = 'If you want sidebar visibility, add a SuperAdmin menu entry + permission key (e.g., view_titancore).';

        // Config checks
        if (!config('titancore.magicai.base_url')) {
            $recs[] = 'MagicAI base URL is not set (TITAN_MAGICAI_BASE_URL). Proxy calls may fail.';
        }
        if (!config('titancore.magicai.api_key')) {
            $recs[] = 'MagicAI API key is not set (TITAN_MAGICAI_API_KEY). Proxy calls may fail.';
        }

        return response()->json($recs);
    }
}
