<?php

namespace Modules\TitanCore\AI;

/**
 * Tool permission gate.
 *
 * Resolves whether the user carried in the execution context holds the
 * permission required to run a specific tool.
 *
 * Tool → permission mappings are supplied at construction time as a plain
 * PHP array:
 *
 *   [
 *     'crm.create_lead'         => 'manage_leads',
 *     'calendar.create_booking' => 'manage_bookings',
 *   ]
 *
 * Tools that are NOT listed in the map are considered unrestricted and
 * will always pass the gate.
 *
 * The gate is compatible with both Laravel Gate (`can()`) and
 * Spatie Permission (`hasPermissionTo()`).  Pass the resolved user model
 * inside the `$context['user']` key when calling the tool executor.
 *
 * The class implements `__invoke` so it can be passed directly as the
 * `$permissionChecker` callable to {@see \Modules\TitanCore\AI\ToolExecutor}.
 */
class ToolPermissionGate
{
    /**
     * @param  array<string, string>  $toolPermissions  Map of tool slug → permission slug.
     */
    public function __construct(
        private readonly array $toolPermissions = []
    ) {}

    /**
     * Determine whether the context user may execute the named tool.
     *
     * @param  string  $toolName  Tool slug as declared in the manifest.
     * @param  array   $context   Execution context — must include 'user' key when
     *                            the tool has a declared permission requirement.
     */
    public function allows(string $toolName, array $context): bool
    {
        $required = $this->toolPermissions[$toolName] ?? null;

        if ($required === null) {
            return true; // no restriction declared for this tool
        }

        $user = $context['user'] ?? null;

        if ($user === null) {
            return false; // permission required but no user in context
        }

        // Spatie Permission (preferred when available)
        if (method_exists($user, 'hasPermissionTo')) {
            return (bool) $user->hasPermissionTo($required);
        }

        // Laravel Gate / Authorizable
        if (method_exists($user, 'can')) {
            return (bool) $user->can($required);
        }

        return false;
    }

    /**
     * Callable interface — compatible with the `$permissionChecker` slot in ToolExecutor.
     */
    public function __invoke(string $toolName, array $context): bool
    {
        return $this->allows($toolName, $context);
    }
}
