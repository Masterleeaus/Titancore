<?php

namespace Modules\TitanCore\Tests\Unit;

use Modules\TitanCore\AI\ValueObjects\ToolContext;
use Modules\TitanCore\AI\ValueObjects\ToolResult;
use Modules\TitanCore\Events\AiRequestCompleted;
use Modules\TitanCore\Exceptions\AI\ToolInputValidationException;
use Modules\TitanCore\Exceptions\AI\ToolNotAllowedException;
use PHPUnit\Framework\TestCase;

class TitanSdkExtractionTest extends TestCase
{
    private const MODULE_ROOT = __DIR__ . '/../..';
    private const SDK_ROOT = self::MODULE_ROOT . '/TitanSDK';

    public function test_sdk_package_scaffold_exists(): void
    {
        $this->assertFileExists(self::SDK_ROOT . '/composer.json');
        $this->assertFileExists(self::SDK_ROOT . '/README.md');
        $this->assertFileExists(self::SDK_ROOT . '/CHANGELOG.md');
        $this->assertFileExists(self::SDK_ROOT . '/LICENSE');
        $this->assertFileExists(self::SDK_ROOT . '/src/Providers/TitanSdkServiceProvider.php');
        $this->assertFileExists(self::SDK_ROOT . '/src/Facades/TitanAI.php');
    }

    public function test_legacy_public_types_remain_compatible_with_sdk_types(): void
    {
        $this->assertTrue(is_subclass_of(
            \Modules\TitanCore\Contracts\AI\ChatProviderContract::class,
            \TitanSDK\Contracts\AI\ChatProviderContract::class,
            true,
        ));
        $this->assertTrue(is_subclass_of(
            \Modules\TitanCore\Contracts\AI\ToolExecutorContract::class,
            \TitanSDK\Contracts\AI\ToolExecutorContract::class,
            true,
        ));
        $this->assertTrue(is_subclass_of(AiRequestCompleted::class, \TitanSDK\Events\AiRequestCompleted::class, true));
        $this->assertTrue(is_subclass_of(ToolNotAllowedException::class, \TitanSDK\Exceptions\AI\ToolNotAllowedException::class, true));
        $this->assertTrue(is_subclass_of(ToolInputValidationException::class, \TitanSDK\Exceptions\AI\ToolInputValidationException::class, true));

        $context = ToolContext::fromArray([
            'user_id' => 5,
            'company_id' => 9,
            'dry_run' => true,
            'correlation_id' => 'corr-sdk',
            'call_stack' => ['tool.a'],
            'meta' => ['source' => 'test'],
        ]);
        $result = new ToolResult(true, 'sdk.tool', ['ok' => true], 'ok');

        $this->assertInstanceOf(\TitanSDK\ValueObjects\ToolContext::class, $context);
        $this->assertInstanceOf(\TitanSDK\ValueObjects\ToolResult::class, $result);
        $this->assertSame('corr-sdk', $context->correlationId);
        $this->assertSame('sdk.tool', $result->tool);
    }

    public function test_sdk_manifest_copies_match_public_titancore_manifests(): void
    {
        $pairs = [
            'module.json' => 'module.json',
            'AI/asset.json' => 'manifests/AI/asset.json',
            'AI/Agents/agent.json' => 'manifests/AI/Agents/agent.json',
            'AI/Prompts/prompt.json' => 'manifests/AI/Prompts/prompt.json',
            'AI/Providers/provider.json' => 'manifests/AI/Providers/provider.json',
            'AI/Tools/tool.json' => 'manifests/AI/Tools/tool.json',
            'AI/Workflows/workflow.json' => 'manifests/AI/Workflows/workflow.json',
            'AI/Engines/engine.json' => 'manifests/AI/Engines/engine.json',
        ];

        foreach ($pairs as $source => $copy) {
            $this->assertSame(
                file_get_contents(self::MODULE_ROOT . '/' . $source),
                file_get_contents(self::SDK_ROOT . '/' . $copy),
                sprintf('Expected SDK manifest copy for %s to match the TitanCore source.', $source),
            );
        }
    }

    public function test_sdk_composer_configuration_declares_psr4_and_service_provider(): void
    {
        $sdkComposer = json_decode((string) file_get_contents(self::SDK_ROOT . '/composer.json'), true, 512, JSON_THROW_ON_ERROR);
        $moduleComposer = json_decode((string) file_get_contents(self::MODULE_ROOT . '/composer.json'), true, 512, JSON_THROW_ON_ERROR);

        $this->assertSame('TitanSDK\\', array_key_first($sdkComposer['autoload']['psr-4']));
        $this->assertSame('src/', $sdkComposer['autoload']['psr-4']['TitanSDK\\']);
        $this->assertContains(
            'TitanSDK\\Providers\\TitanSdkServiceProvider',
            $sdkComposer['extra']['laravel']['providers'],
        );
        $this->assertSame('TitanSDK/src/', $moduleComposer['autoload']['psr-4']['TitanSDK\\']);
    }
}
