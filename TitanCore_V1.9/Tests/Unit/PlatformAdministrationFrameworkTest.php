<?php

namespace Modules\TitanCore\Tests\Unit;

use Modules\TitanCore\Support\Filament\PlatformAdministrationFramework;
use PHPUnit\Framework\TestCase;

class PlatformAdministrationFrameworkTest extends TestCase
{
    public function test_filters_panels_by_permission(): void
    {
        $framework = new PlatformAdministrationFramework([
            'panels' => [
                'super_admin' => ['permission' => 'super-admin'],
                'platform_admin' => ['permission' => 'manage_ai'],
            ],
        ]);

        $user = new class {
            public function hasRole(string $role): bool
            {
                return $role === 'super-admin';
            }

            public function can(string $permission): bool
            {
                return $permission === 'manage_ai';
            }
        };

        $panels = $framework->availablePanelsFor($user);

        $this->assertArrayHasKey('super_admin', $panels);
        $this->assertArrayHasKey('platform_admin', $panels);
    }

    public function test_panel_switcher_returns_only_allowed_configured_panels(): void
    {
        $framework = new PlatformAdministrationFramework([
            'panel_switcher' => [
                'enabled' => true,
                'panels' => ['super_admin', 'platform_admin'],
            ],
            'panels' => [
                'super_admin' => ['permission' => 'super-admin'],
                'platform_admin' => ['permission' => 'manage_ai'],
            ],
        ]);

        $user = new class {
            public function hasRole(string $role): bool
            {
                return false;
            }

            public function can(string $permission): bool
            {
                return $permission === 'manage_ai';
            }
        };

        $this->assertSame(['platform_admin'], $framework->panelSwitcher($user));
    }
}
