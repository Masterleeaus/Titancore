<?php

namespace Modules\TitanCore\Tests\Unit;

use Modules\TitanCore\Services\Engine\EngineLifecycle;
use Modules\TitanCore\Services\Engine\EngineRegistry;
use Modules\TitanCore\Services\Engine\EngineValidator;
use Modules\TitanCore\Support\AssetManifestValidator;
use PHPUnit\Framework\TestCase;

class EngineFrameworkTest extends TestCase
{
    public function test_engine_manifest_type_validates_successfully(): void
    {
        $validator = new AssetManifestValidator();
        $manifest = [
            'name' => 'Engines',
            'version' => '1.0.0',
            'description' => 'Engine registry',
            'module' => 'TitanCore',
            'capabilities' => ['engine-framework'],
            'discovery_metadata' => ['asset_type' => 'engine_registry'],
            'engines' => [[
                'id' => 'engine_a',
                'name' => 'Engine A',
                'type' => 'runtime',
                'description' => 'Test engine',
                'class' => 'Modules\\TitanCore\\Services\\TitanCoreModelGateway',
                'version' => '1.0.0',
                'sdk_version' => '1.0.0',
                'author' => 'TitanCore',
                'lifecycle' => 'registered',
                'dependencies' => [],
                'permissions' => ['ai.chat'],
                'capabilities' => ['chat'],
                'providers' => ['titancore.gateway'],
                'widgets' => [],
                'resources' => [],
                'settings' => ['default_provider_strategy' => 'gateway'],
                'health_checks' => ['gateway_connectivity'],
                'upgrade_handlers' => ['Modules\\TitanCore\\Services\\Engine\\EngineLifecycle::transition'],
                'install_handlers' => ['Modules\\TitanCore\\Services\\Engine\\EngineInstaller::install'],
            ]],
        ];

        $result = $validator->validateData($manifest, 'engine', 'engine.json');

        $this->assertTrue($result->isValid(), implode('; ', $result->allMessages()));
    }

    public function test_engine_registry_sync_and_find(): void
    {
        $registry = new EngineRegistry();
        $registry->sync([
            ['id' => 'engine_a', 'name' => 'Engine A'],
            ['id' => 'engine_b', 'name' => 'Engine B'],
        ]);

        $this->assertCount(2, $registry->all());
        $this->assertSame('Engine A', $registry->find('engine_a')['name']);
        $this->assertNull($registry->find('missing'));
    }

    public function test_engine_lifecycle_transition_updates_status(): void
    {
        $lifecycle = new EngineLifecycle();
        $engine = ['id' => 'engine_a', 'status' => 'installed'];

        $updated = $lifecycle->transition($engine, 'active');

        $this->assertSame('active', $updated['status']);
        $this->assertArrayHasKey('lifecycle_updated_at', $updated);
    }

    public function test_engine_lifecycle_supports_required_states(): void
    {
        $lifecycle = new EngineLifecycle();
        $engine = ['id' => 'engine_a', 'status' => 'installed'];

        $requiredStates = ['installed', 'registered', 'validated', 'initialized', 'ready', 'active', 'maintenance', 'upgrading', 'disabled', 'failed', 'removed'];

        foreach ($requiredStates as $state) {
            $updated = $lifecycle->transition($engine, $state);
            $this->assertSame($state, $updated['status']);
        }
    }

    public function test_engine_validator_reports_missing_required_fields(): void
    {
        $validator = new EngineValidator(new AssetManifestValidator());

        $result = $validator->validateEngine([
            'id' => 'engine_a',
            'name' => 'Engine A',
        ]);

        $this->assertFalse($result['valid']);
        $this->assertNotEmpty($result['errors']);
    }
}
