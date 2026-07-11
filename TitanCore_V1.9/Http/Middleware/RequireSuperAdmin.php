<?php

namespace Modules\TitanCore\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class RequireSuperAdmin
{
    public function handle(Request $request, Closure $next)
    {
        $u = user();
        $isSuperAdmin = false;

        if ($u) {
            if (method_exists($u, 'isSuperAdmin')) {
                $isSuperAdmin = (bool) $u->isSuperAdmin();
            } elseif (property_exists($u, 'is_superadmin')) {
                $isSuperAdmin = (bool) $u->is_superadmin;
            } elseif (property_exists($u, 'role_id')) {
                $isSuperAdmin = ((int) $u->role_id === 1);
            }
        }

        abort_unless($isSuperAdmin, 403);

        return $next($request);
    }
}
