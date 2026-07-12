<?php

namespace Modules\TitanCore\Support\Filament;

/**
 * PlatformSettingsFramework — metadata-driven settings system for the Platform Studio.
 *
 * Settings are organised into groups. Each group is gated by a permission and
 * may contain any number of fields. The framework itself does not persist values;
 * it provides structure and validation contracts for consumer implementations.
 */
class PlatformSettingsFramework
{
    /** @var array<string, array<string, mixed>> */
    private array $groups;

    /**
     * @param  array<string, mixed>  $config  The 'titancore.filament.settings_framework' config slice.
     */
    public function __construct(array $config = [])
    {
        $raw = (array) ($config['groups'] ?? []);

        $this->groups = [];

        foreach ($raw as $group) {
            if (is_array($group) && isset($group['key'])) {
                $this->groups[(string) $group['key']] = $group;
            }
        }
    }

    /**
     * Return all registered group definitions.
     *
     * @return array<string, array<string, mixed>>
     */
    public function groups(): array
    {
        return $this->groups;
    }

    /**
     * Return the group definition for a single key, or an empty array if not found.
     *
     * @return array<string, mixed>
     */
    public function group(string $key): array
    {
        return (array) ($this->groups[$key] ?? []);
    }

    /**
     * Return groups accessible to the given user.
     *
     * @return array<string, array<string, mixed>>
     */
    public function groupsFor(mixed $user): array
    {
        return array_filter($this->groups, function (array $group) use ($user): bool {
            return $this->can($user, $group['permission'] ?? null);
        });
    }

    /**
     * Return the icon name for a group, or null when not defined.
     */
    public function groupIcon(string $key): ?string
    {
        $value = $this->groups[$key]['icon'] ?? null;

        return is_string($value) ? $value : null;
    }

    /**
     * Return the human-readable label for a group, or the key when not defined.
     */
    public function groupLabel(string $key): string
    {
        $label = $this->groups[$key]['label'] ?? null;

        return is_string($label) ? $label : ucfirst(str_replace('_', ' ', $key));
    }

    /**
     * Check whether a group key is registered.
     */
    public function has(string $key): bool
    {
        return isset($this->groups[$key]);
    }

    /**
     * Return all registered group keys.
     *
     * @return string[]
     */
    public function keys(): array
    {
        return array_keys($this->groups);
    }

    // ──────────────────────────────────────────────────────────────────────────

    private function can(mixed $user, ?string $permission): bool
    {
        if (empty($permission)) {
            return true;
        }

        if (method_exists($user, 'can') && (bool) $user->can($permission)) {
            return true;
        }

        return method_exists($user, 'hasRole') ? (bool) $user->hasRole($permission) : false;
    }
}
