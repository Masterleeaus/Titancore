<?php

namespace Modules\TitanCore\Tests\Unit;

use Modules\TitanCore\Http\Controllers\Api\PromptApiController;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

class PromptApiControllerTest extends TestCase
{
    public function test_decode_json_to_array_returns_null_for_invalid_or_scalar_json(): void
    {
        $controller = new PromptApiController();
        $method = new ReflectionMethod($controller, 'decodeJsonToArray');
        $method->setAccessible(true);

        $this->assertNull($method->invoke($controller, '{ invalid json'));
        $this->assertNull($method->invoke($controller, '123'));
    }

    public function test_decode_json_to_array_returns_array_for_object_json(): void
    {
        $controller = new PromptApiController();
        $method = new ReflectionMethod($controller, 'decodeJsonToArray');
        $method->setAccessible(true);

        $this->assertSame(['title' => 'Hello'], $method->invoke($controller, '{"title":"Hello"}'));
    }

    public function test_normalize_metadata_ignores_invalid_existing_json_and_merges_valid_metadata(): void
    {
        $controller = new PromptApiController();
        $method = new ReflectionMethod($controller, 'normalizeMetadata');
        $method->setAccessible(true);

        $existing = (object) ['metadata' => '{ invalid json'];
        $result = $method->invoke($controller, ['title' => 'First', 'metadata' => ['topic' => 'ai']], $existing);
        $this->assertSame(['topic' => 'ai', 'title' => 'First'], $result);

        $existing = (object) ['metadata' => '{"title":"Old","category":"core"}'];
        $result = $method->invoke($controller, ['metadata' => ['category' => 'updated']], $existing);
        $this->assertSame(['title' => 'Old', 'category' => 'updated'], $result);
    }
}
