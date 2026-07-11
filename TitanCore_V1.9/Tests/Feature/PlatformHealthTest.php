<?php

namespace Modules\TitanCore\Tests\Feature;

use Tests\TestCase;

/**
 * Feature tests for the platform health endpoint.
 *
 * GET /api/v1/platform/health — authenticated, super-admin only.
 */
class PlatformHealthTest extends TestCase
{
    /** @test */
    public function platform_health_route_exists_and_requires_auth(): void
    {
        $response = $this->getJson('/api/v1/platform/health');

        // Unauthenticated request must return 401 or 302 (redirect to login)
        $this->assertContains($response->getStatusCode(), [401, 302, 403]);
    }
}
