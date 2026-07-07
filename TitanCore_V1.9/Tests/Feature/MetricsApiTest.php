<?php

namespace Modules\TitanCore\Tests\Feature;

use Tests\TestCase;

class MetricsApiTest extends TestCase
{
    /** @test */
    public function usage_and_metrics_routes_exist()
    {
        $resp1 = $this->get('/api/titancore/usage');
        $resp2 = $this->get('/api/titancore/metrics');
        $this->assertTrue(in_array($resp1->status(), [200, 302, 401, 403]));
        $this->assertTrue(in_array($resp2->status(), [200, 302, 401, 403]));
    }
}
