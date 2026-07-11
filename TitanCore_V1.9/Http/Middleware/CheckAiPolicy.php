<?php

namespace Modules\TitanCore\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class CheckAiPolicy
{
    public function handle(Request $request, Closure $next): mixed
    {
        $tenantId = $request->user()?->tenant_id ?? null;
        $model = data_get($request->all(), 'options.model');

        if (!$model) {
            return $next($request);
        }

        $allow = config('titancore.policies.overrides.' . $tenantId, config('titancore.policies.default_allow', []));

        if (!is_array($allow) || !in_array($model, $allow, true)) {
            return response()->json(['error' => "Model {$model} not allowed for this tenant"], 403);
        }

        return $next($request);
    }
}