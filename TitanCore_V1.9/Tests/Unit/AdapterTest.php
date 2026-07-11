<?php

namespace Modules\TitanCore\Tests\Unit;

use Modules\TitanCore\AI\Adapters\OpenAIClient;
use PHPUnit\Framework\TestCase;

class AdapterTest extends TestCase
{
    public function test_openai_health_without_key_is_not_ok()
    {
        $client = new OpenAIClient();
        $health = $client->health();
        $this->assertArrayHasKey('ok', $health);
    }
}
