<?php

namespace Modules\TitanCore\Tests\Unit;

use Modules\TitanCore\Support\Filament\DashboardFramework;
use Modules\TitanCore\Support\Filament\DeveloperToolsFramework;
use Modules\TitanCore\Support\Filament\PlatformAdministrationFramework;
use Modules\TitanCore\Support\Filament\PlatformSettingsFramework;
use Modules\TitanCore\Support\Filament\PlatformStudio;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for the Platform Studio sprint components.
 *
 * Covers:
 *  - Phase 2: PlatformStudio orchestrator
 *  - Phase 3: DashboardFramework
 *  - Phase 4: PlatformSettingsFramework
 *  - Phase 9: DeveloperToolsFramework
 */
class PlatformStudioTest extends TestCase
{
    // ── Helpers ───────────────────────────────────────────────────────────────

    private function superUser(): object
    {
        return new class {
            public function hasRole(string $role): bool
            {
                return $role === 'super-admin';
            }

            public function can(string $permission): bool
            {
                return in_array($permission, ['manage_platform', 'manage_ai', 'developer_access'], true);
            }
        };
    }

    private function noPermUser(): object
    {
        return new class {
            public function hasRole(string $role): bool
            {
                return false;
            }

            public function can(string $permission): bool
            {
                return false;
            }
        };
    }

    private function buildFramework(): PlatformAdministrationFramework
    {
        return new PlatformAdministrationFramework([
            'panel_switcher' => [
                'enabled' => true,
                'panels' => ['super_admin', 'platform_studio', 'developer'],
            ],
            'panels' => [
                'super_admin'     => ['permission' => 'super-admin'],
                'platform_studio' => [
                    'permission' => 'manage_platform',
                    'navigation' => [
                        ['label' => 'Dashboard',      'group' => null],
                        ['label' => 'AI Providers',   'group' => 'AI', 'permission' => 'manage_ai'],
                        ['label' => 'Developer Only', 'group' => 'Dev', 'permission' => 'developer_access'],
                    ],
                ],
                'developer' => [
                    'permission' => 'developer_access',
                    'navigation' => [
                        ['label' => 'SDK Explorer', 'group' => 'SDK'],
                    ],
                ],
            ],
            'dashboard_framework' => [
                'dashboards' => [
                    'platform_studio' => [
                        'widgets' => ['platform_health_status', 'platform_metrics'],
                    ],
                    'developer' => [
                        'widgets' => ['platform_status_indicators'],
                    ],
                ],
            ],
            'settings_framework' => [
                'groups' => [
                    ['key' => 'ai', 'label' => 'AI', 'permission' => 'manage_ai', 'icon' => 'sparkles'],
                    ['key' => 'developer', 'label' => 'Developer', 'permission' => 'developer_access', 'icon' => 'code'],
                ],
            ],
        ]);
    }

    // ── PlatformStudio ────────────────────────────────────────────────────────

    public function test_studio_navigation_returned_for_permitted_user(): void
    {
        $studio = new PlatformStudio($this->buildFramework());
        $items  = $studio->studioNavigation($this->superUser());

        $labels = array_column($items, 'label');

        $this->assertContains('Dashboard', $labels);
        $this->assertContains('AI Providers', $labels);
        $this->assertContains('Developer Only', $labels);
    }

    public function test_studio_navigation_empty_for_unpermitted_user(): void
    {
        $studio = new PlatformStudio($this->buildFramework());

        // 'Dashboard' has no explicit permission so it should pass through
        $items = $studio->studioNavigation($this->noPermUser());

        $labels = array_column($items, 'label');

        $this->assertContains('Dashboard', $labels);
        $this->assertNotContains('AI Providers', $labels);
        $this->assertNotContains('Developer Only', $labels);
    }

    public function test_studio_navigation_by_group_indexes_correctly(): void
    {
        $studio  = new PlatformStudio($this->buildFramework());
        $grouped = $studio->studioNavigationByGroup($this->superUser());

        $this->assertArrayHasKey('AI', $grouped);
        $this->assertArrayHasKey('Dev', $grouped);

        $aiLabels = array_column($grouped['AI'], 'label');
        $this->assertContains('AI Providers', $aiLabels);
    }

    public function test_studio_dashboard_widgets_returned(): void
    {
        $studio   = new PlatformStudio($this->buildFramework());
        $widgets  = $studio->studioDashboardWidgets();

        $this->assertContains('platform_health_status', $widgets);
        $this->assertContains('platform_metrics', $widgets);
    }

    public function test_developer_dashboard_widgets_returned(): void
    {
        $studio  = new PlatformStudio($this->buildFramework());
        $widgets = $studio->developerDashboardWidgets();

        $this->assertContains('platform_status_indicators', $widgets);
    }

    public function test_available_panels_respects_permissions(): void
    {
        $studio = new PlatformStudio($this->buildFramework());
        $panels = $studio->availablePanels($this->superUser());

        $this->assertContains('platform_studio', $panels);
        $this->assertContains('developer', $panels);
        $this->assertNotContains('super_admin', $panels);
    }

    public function test_settings_groups_filtered_by_permission(): void
    {
        $studio = new PlatformStudio($this->buildFramework());
        $groups = $studio->settingsGroups($this->superUser());

        $keys = array_column($groups, 'key');
        $this->assertContains('ai', $keys);
        $this->assertContains('developer', $keys);
    }

    // ── DashboardFramework ────────────────────────────────────────────────────

    public function test_dashboard_framework_widgets_for_dashboard(): void
    {
        $framework = new DashboardFramework([
            'widget_types' => [
                'chart'  => ['label' => 'Chart', 'resizable' => true],
                'metric' => ['label' => 'Metric', 'resizable' => false],
            ],
            'dashboards' => [
                'my_dash' => ['widgets' => ['platform_health_status', 'platform_metrics']],
            ],
        ]);

        $this->assertSame(['platform_health_status', 'platform_metrics'], $framework->widgets('my_dash'));
        $this->assertSame([], $framework->widgets('nonexistent'));
    }

    public function test_dashboard_framework_has_dashboard(): void
    {
        $framework = new DashboardFramework([
            'dashboards' => ['a' => ['widgets' => []]],
        ]);

        $this->assertTrue($framework->hasDashboard('a'));
        $this->assertFalse($framework->hasDashboard('b'));
    }

    public function test_dashboard_framework_widget_type_resizable(): void
    {
        $framework = new DashboardFramework([
            'widget_types' => [
                'chart'  => ['label' => 'Chart',  'resizable' => true],
                'metric' => ['label' => 'Metric', 'resizable' => false],
            ],
        ]);

        $this->assertTrue($framework->isResizable('chart'));
        $this->assertFalse($framework->isResizable('metric'));
        $this->assertFalse($framework->isResizable('unknown'));
    }

    public function test_dashboard_framework_dashboard_keys(): void
    {
        $framework = new DashboardFramework([
            'dashboards' => [
                'super_admin'    => ['widgets' => []],
                'platform_studio' => ['widgets' => []],
            ],
        ]);

        $this->assertEqualsCanonicalizing(['super_admin', 'platform_studio'], $framework->dashboardKeys());
    }

    // ── PlatformSettingsFramework ──────────────────────────────────────────────

    public function test_settings_framework_returns_all_groups(): void
    {
        $framework = new PlatformSettingsFramework([
            'groups' => [
                ['key' => 'general', 'label' => 'General', 'permission' => 'manage_platform', 'icon' => 'cog'],
                ['key' => 'ai',      'label' => 'AI',      'permission' => 'manage_ai',       'icon' => 'sparkles'],
            ],
        ]);

        $this->assertCount(2, $framework->groups());
        $this->assertTrue($framework->has('general'));
        $this->assertTrue($framework->has('ai'));
        $this->assertFalse($framework->has('nonexistent'));
    }

    public function test_settings_framework_groups_for_user(): void
    {
        $framework = new PlatformSettingsFramework([
            'groups' => [
                ['key' => 'general',   'label' => 'General',   'permission' => 'manage_platform'],
                ['key' => 'developer', 'label' => 'Developer',  'permission' => 'developer_access'],
            ],
        ]);

        $user = new class {
            public function can(string $p): bool { return $p === 'manage_platform'; }
            public function hasRole(string $r): bool { return false; }
        };

        $groups = $framework->groupsFor($user);
        $keys   = array_column(array_values($groups), 'key');

        $this->assertContains('general', $keys);
        $this->assertNotContains('developer', $keys);
    }

    public function test_settings_framework_group_label_fallback(): void
    {
        $framework = new PlatformSettingsFramework([
            'groups' => [
                ['key' => 'my_group'],
            ],
        ]);

        $this->assertSame('My group', $framework->groupLabel('my_group'));
    }

    public function test_settings_framework_group_icon(): void
    {
        $framework = new PlatformSettingsFramework([
            'groups' => [
                ['key' => 'ai', 'label' => 'AI', 'icon' => 'sparkles'],
            ],
        ]);

        $this->assertSame('sparkles', $framework->groupIcon('ai'));
        $this->assertNull($framework->groupIcon('nonexistent'));
    }

    // ── DeveloperToolsFramework ───────────────────────────────────────────────

    public function test_developer_tools_register_and_retrieve(): void
    {
        $framework = new DeveloperToolsFramework();
        $framework->register('developer_sdk_explorer', [
            'label'       => 'SDK Explorer',
            'description' => 'Browse SDK contracts.',
            'group'       => 'SDK',
        ]);

        $this->assertTrue($framework->has('developer_sdk_explorer'));
        $tool = $framework->tool('developer_sdk_explorer');

        $this->assertNotNull($tool);
        $this->assertSame('SDK Explorer', $tool['label']);
        $this->assertSame('SDK', $tool['group']);
        $this->assertTrue($tool['available']);
    }

    public function test_developer_tools_disable_marks_unavailable(): void
    {
        $framework = new DeveloperToolsFramework();
        $framework->register('developer_route_explorer', ['label' => 'Route Explorer', 'group' => 'Exploration']);
        $framework->disable('developer_route_explorer');

        $tool = $framework->tool('developer_route_explorer');
        $this->assertFalse($tool['available']);

        $available = $framework->availableTools();
        $this->assertArrayNotHasKey('developer_route_explorer', $available);
    }

    public function test_developer_tools_by_group(): void
    {
        $framework = new DeveloperToolsFramework();
        $framework->register('developer_sdk_explorer',      ['label' => 'SDK Explorer',      'group' => 'SDK']);
        $framework->register('developer_contract_explorer', ['label' => 'Contract Explorer', 'group' => 'SDK']);
        $framework->register('developer_route_explorer',    ['label' => 'Route Explorer',    'group' => 'Exploration']);

        $grouped = $framework->toolsByGroup();

        $this->assertArrayHasKey('SDK', $grouped);
        $this->assertArrayHasKey('Exploration', $grouped);
        $this->assertCount(2, $grouped['SDK']);
        $this->assertCount(1, $grouped['Exploration']);
    }

    public function test_developer_tools_keys(): void
    {
        $framework = new DeveloperToolsFramework();
        $framework->register('developer_sdk_explorer', ['label' => 'SDK Explorer']);
        $framework->register('developer_api_explorer', ['label' => 'API Explorer']);

        $this->assertEqualsCanonicalizing(
            ['developer_sdk_explorer', 'developer_api_explorer'],
            $framework->keys()
        );
    }
}
