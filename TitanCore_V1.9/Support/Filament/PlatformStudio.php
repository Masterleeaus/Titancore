<?php

namespace Modules\TitanCore\Support\Filament;

/**
 * PlatformStudio — central orchestrator for the Titan Platform Operating System UI.
 *
 * Provides access to all Studio navigation, panels, widgets and page keys
 * driven entirely by the config under 'titancore.filament'.
 *
 * The Studio itself does not contain business logic. It reads metadata and
 * delegates execution to the appropriate services.
 */
class PlatformStudio
{
    public function __construct(private readonly PlatformAdministrationFramework $framework)
    {
    }

    /**
     * Return the navigation items for the Platform Studio panel, filtered by user permissions.
     *
     * @return array<int, array<string, mixed>>
     */
    public function studioNavigation(mixed $user): array
    {
        return $this->filteredNavigation('platform_studio', $user);
    }

    /**
     * Return the navigation items for the Developer panel, filtered by user permissions.
     *
     * @return array<int, array<string, mixed>>
     */
    public function developerNavigation(mixed $user): array
    {
        return $this->filteredNavigation('developer', $user);
    }

    /**
     * Return navigation items for any panel, filtered by user permissions.
     *
     * @return array<int, array<string, mixed>>
     */
    public function filteredNavigation(string $panelKey, mixed $user): array
    {
        $items = $this->framework->navigation($panelKey);

        return array_values(array_filter($items, function (array $item) use ($user): bool {
            $permission = $item['permission'] ?? null;

            return $this->can($user, $permission);
        }));
    }

    /**
     * Return navigation groups for the Platform Studio panel.
     *
     * Items are indexed by group label; ungrouped items use an empty string key.
     *
     * @return array<string, array<int, array<string, mixed>>>
     */
    public function studioNavigationByGroup(mixed $user): array
    {
        $items  = $this->studioNavigation($user);
        $groups = [];

        foreach ($items as $item) {
            $group = $item['group'] ?? '';
            $groups[$group][] = $item;
        }

        return $groups;
    }

    /**
     * Return the dashboard widget keys for the Platform Studio dashboard.
     *
     * @return string[]
     */
    public function studioDashboardWidgets(): array
    {
        return $this->framework->dashboardWidgets('platform_studio');
    }

    /**
     * Return the dashboard widget keys for the Developer dashboard.
     *
     * @return string[]
     */
    public function developerDashboardWidgets(): array
    {
        return $this->framework->dashboardWidgets('developer');
    }

    /**
     * Return the panel switcher list for the given user across all configured panels.
     *
     * @return string[]
     */
    public function availablePanels(mixed $user): array
    {
        return $this->framework->panelSwitcher($user);
    }

    /**
     * Return settings groups visible to the given user across all configured groups.
     *
     * @return array<int, array<string, mixed>>
     */
    public function settingsGroups(mixed $user): array
    {
        return $this->framework->settingsGroupsFor($user);
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
