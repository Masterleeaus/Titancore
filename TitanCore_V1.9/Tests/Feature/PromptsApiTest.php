<?php

namespace Modules\TitanCore\Tests\Feature;

use Tests\TestCase;

class PromptsApiTest extends TestCase
{
    /** @test */
    public function prompts_index_route_exists()
    {
        $resp = $this->get('/api/titancore/prompts');
        $this->assertTrue(in_array($resp->status(), [200, 302, 401, 403]));
    }
}
