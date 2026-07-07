<?php

namespace Modules\TitanCore\Tests\Feature;

use Tests\TestCase;

class RoutesTest extends TestCase
{
    /** @test */
    public function health_status_route_exists()
    {
        $response = $this->get('/api/titancore/status');
        $this->assertTrue(in_array($response->status(), [200, 302, 401, 403]));
    }
}
