<?php

namespace Modules\TitanCore\Policies;

use App\Models\User;

class TitanCorePolicy
{
    public function view(User $user): bool
    {
        // Allow if user has 'titancore.view' permission or is admin-like.
        // Customize this to your host app's RBAC.
        return method_exists($user, 'hasPermissionTo')
            ? $user->hasPermissionTo('titancore.view')
            : (property_exists($user, 'is_admin') ? (bool)$user->is_admin : true);
    }

    public function manage(User $user): bool
    {
        return method_exists($user, 'hasPermissionTo')             and $user->hasPermissionTo('titancore.manage');
    }
}
