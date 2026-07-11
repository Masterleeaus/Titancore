<?php

namespace Modules\TitanCore\Support\Filament;

class PlatformAdministrationFramework
{
    public function __construct(private readonly array $framework = [])
    {
    }

    public function panels(): array
    {
        return (array) ($this->framework['panels'] ?? []);
    }

    public function panel(string $key): array
    {
        return (array) ($this->panels()[$key] ?? []);
    }

    public function availablePanelsFor(mixed $user): array
    {
        $panels = [];

        foreach ($this->panels() as $key => $panel) {
            $permission = $panel['permission'] ?? null;
            if ($this->can($user, $permission)) {
                $panels[$key] = $panel;
            }
        }

        return $panels;
    }

    public function navigation(string $panel): array
    {
        return (array) ($this->panel($panel)['navigation'] ?? []);
    }

    public function globalSearch(string $panel): array
    {
        return (array) ($this->panel($panel)['global_search'] ?? []);
    }

    public function panelSwitcher(mixed $user): array
    {
        $switcher = (array) ($this->framework['panel_switcher'] ?? []);
        $enabled = (bool) ($switcher['enabled'] ?? false);

        if (! $enabled) {
            return [];
        }

        $allowedPanels = array_keys($this->availablePanelsFor($user));
        $configuredPanels = (array) ($switcher['panels'] ?? []);

        return array_values(array_intersect($configuredPanels, $allowedPanels));
    }

    public function settingsGroupsFor(mixed $user): array
    {
        $groups = (array) ($this->framework['settings_framework']['groups'] ?? []);

        return array_values(array_filter($groups, function (array $group) use ($user): bool {
            return $this->can($user, $group['permission'] ?? null);
        }));
    }

    public function dashboardWidgets(string $dashboard): array
    {
        return (array) ($this->framework['dashboard_framework']['dashboards'][$dashboard]['widgets'] ?? []);
    }

    private function can(mixed $user, ?string $permission): bool
    {
        if (empty($permission)) {
            return true;
        }

        if ($permission === 'super-admin') {
            return method_exists($user, 'hasRole') ? (bool) $user->hasRole('super-admin') : false;
        }

        return method_exists($user, 'can') ? (bool) $user->can($permission) : false;
    }
}
