<?php

namespace Modules\TitanCore\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class RequireTitanCoreAbility
{
    public function handle(Request $request, Closure $next, $ability = 'titancore')
    {
        $user = $request->user();
        if (method_exists($user, 'currentAccessToken')) {
            $token = $user->currentAccessToken();
            if ($token && !$token->can($ability)) {
                return response()->json(['message' => 'Missing token ability: ' . $ability], 403);
            }
        }
        return $next($request);
    }
}
