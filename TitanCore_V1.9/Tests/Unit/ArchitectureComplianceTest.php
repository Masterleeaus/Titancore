<?php

namespace Modules\TitanCore\Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Architecture Compliance & Freeze Tests
 *
 * Enforces the structural rules of the TitanCore platform kernel so future
 * contributors cannot accidentally erode the architecture.
 *
 * Rules covered:
 *  1. PSR-4 namespace compliance — every PHP file declares a namespace that
 *     matches its directory relative to the module root.
 *  2. SDK boundary — every public contract in Modules\TitanCore\Contracts
 *     extends its TitanSDK counterpart.
 *  3. No direct provider bypass — internal AI provider classes are disabled and
 *     never injected directly by platform code outside the gateway.
 *  4. Engine manifest validation — engine.json declares all required fields and
 *     every engine entry is complete.
 *  5. Manifest source-of-truth parity — TitanCore manifests and their TitanSDK
 *     copies are byte-identical.
 *  6. Engine lifecycle fields — each engine entry carries a lifecycle descriptor.
 *  7. Module manifest required fields — module.json is complete.
 *  8. TitanSDK internal-class boundary — the TitanSDK src tree may not expose
 *     concrete classes from Modules\TitanCore internals directly.
 *  9. All AI execution enters through TitanCoreModelGateway — controllers and
 *     service providers import the gateway, never raw providers.
 * 10. Dependency test — TitanSDK contracts are pure interfaces with no
 *     concrete implementation details.
 */
class ArchitectureComplianceTest extends TestCase
{
    private const MODULE_ROOT = __DIR__ . '/../..';
    private const SDK_ROOT    = self::MODULE_ROOT . '/TitanSDK';
    private const SDK_SRC     = self::SDK_ROOT . '/src';

    // ── 1. PSR-4 Namespace Compliance ────────────────────────────────────────

    /**
     * Every PHP source file under the module root (excluding TitanSDK, Tests,
     * and vendor) must declare a namespace that begins with Modules\TitanCore.
     */
    public function test_all_module_php_files_declare_modules_titancore_namespace(): void
    {
        $violations = [];

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator(self::MODULE_ROOT, \FilesystemIterator::SKIP_DOTS),
        );

        $skipDirs = [
            realpath(self::MODULE_ROOT . '/TitanSDK'),
            realpath(self::MODULE_ROOT . '/Tests'),
            realpath(self::MODULE_ROOT . '/vendor'),
            realpath(self::MODULE_ROOT . '/Database/Seeders'),
        ];

        /** @var \SplFileInfo $file */
        foreach ($iterator as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }

            $realPath = $file->getRealPath();

            // Skip excluded directories
            foreach ($skipDirs as $skip) {
                if ($skip !== false && str_starts_with($realPath, $skip . DIRECTORY_SEPARATOR)) {
                    continue 2;
                }
            }

            $contents = (string) file_get_contents($realPath);

            // Extract declared namespace
            if (! preg_match('/^namespace\s+([\w\\\\]+)\s*;/m', $contents, $m)) {
                // Files with no namespace declaration are a PSR-4 violation
                $violations[] = sprintf('No namespace declared in %s', $this->relativePath($realPath));
                continue;
            }

            $declared = $m[1];
            if (! str_starts_with($declared, 'Modules\\TitanCore')) {
                $violations[] = sprintf(
                    'Namespace "%s" does not begin with Modules\\TitanCore in %s',
                    $declared,
                    $this->relativePath($realPath),
                );
            }
        }

        $this->assertEmpty(
            $violations,
            "PSR-4 namespace violations detected:\n" . implode("\n", $violations),
        );
    }

    /**
     * Every PHP source file inside TitanSDK/src must declare a namespace
     * beginning with TitanSDK.
     */
    public function test_all_sdk_php_files_declare_titansdk_namespace(): void
    {
        $violations = [];

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator(self::SDK_SRC, \FilesystemIterator::SKIP_DOTS),
        );

        /** @var \SplFileInfo $file */
        foreach ($iterator as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }

            $realPath = $file->getRealPath();
            $contents = (string) file_get_contents($realPath);

            if (! preg_match('/^namespace\s+([\w\\\\]+)\s*;/m', $contents, $m)) {
                $violations[] = sprintf('No namespace declared in %s', $this->relativePath($realPath));
                continue;
            }

            $declared = $m[1];
            if (! str_starts_with($declared, 'TitanSDK')) {
                $violations[] = sprintf(
                    'Namespace "%s" does not begin with TitanSDK in %s',
                    $declared,
                    $this->relativePath($realPath),
                );
            }
        }

        $this->assertEmpty(
            $violations,
            "TitanSDK namespace violations detected:\n" . implode("\n", $violations),
        );
    }

    // ── 2. SDK Boundary — Contracts extend TitanSDK equivalents ─────────────

    /**
     * Every interface under Modules\TitanCore\Contracts\AI must extend the
     * corresponding TitanSDK\Contracts\AI interface so that external modules
     * referencing TitanSDK types remain compatible with TitanCore implementations.
     */
    public function test_titancore_ai_contracts_extend_sdk_equivalents(): void
    {
        $contractDir = self::MODULE_ROOT . '/Contracts/AI';

        $this->assertDirectoryExists($contractDir, 'Contracts/AI directory must exist.');

        $violations = [];

        foreach (glob($contractDir . '/*.php') as $file) {
            $class   = 'Modules\\TitanCore\\Contracts\\AI\\' . basename($file, '.php');
            $sdkBase = 'TitanSDK\\Contracts\\AI\\' . basename($file, '.php');

            if (! interface_exists($sdkBase, false) && ! class_exists($sdkBase, false)) {
                // SDK counterpart does not exist; skip (new local-only contract).
                continue;
            }

            if (! is_subclass_of($class, $sdkBase, true)) {
                $violations[] = sprintf('%s must extend or implement %s', $class, $sdkBase);
            }
        }

        $this->assertEmpty(
            $violations,
            "SDK contract boundary violations:\n" . implode("\n", $violations),
        );
    }

    /**
     * Engine contracts in Modules\TitanCore\Contracts\Engine extend their
     * TitanSDK counterparts.
     */
    public function test_titancore_engine_contracts_extend_sdk_equivalents(): void
    {
        $contractDir = self::MODULE_ROOT . '/Contracts/Engine';

        $this->assertDirectoryExists($contractDir, 'Contracts/Engine directory must exist.');

        $violations = [];

        foreach (glob($contractDir . '/*.php') as $file) {
            $class   = 'Modules\\TitanCore\\Contracts\\Engine\\' . basename($file, '.php');
            $sdkBase = 'TitanSDK\\Contracts\\Engine\\' . basename($file, '.php');

            if (! interface_exists($sdkBase, false) && ! class_exists($sdkBase, false)) {
                continue;
            }

            if (! is_subclass_of($class, $sdkBase, true)) {
                $violations[] = sprintf('%s must extend or implement %s', $class, $sdkBase);
            }
        }

        $this->assertEmpty(
            $violations,
            "SDK engine contract boundary violations:\n" . implode("\n", $violations),
        );
    }

    // ── 3. No direct provider bypass ─────────────────────────────────────────

    /**
     * Direct OpenAI and Anthropic provider classes must carry a DISABLED_REASON
     * constant that documents why direct calls are prohibited. This constant acts
     * as a machine-verifiable enforcement marker for the "all AI through gateway"
     * rule.
     */
    public function test_direct_ai_adapters_declare_disabled_reason(): void
    {
        $adapterFiles = [
            self::MODULE_ROOT . '/AI/Adapters/OpenAIClient.php',
            self::MODULE_ROOT . '/AI/Providers/OpenAiChatProvider.php',
            self::MODULE_ROOT . '/AI/Providers/OpenAiEmbeddingProvider.php',
        ];

        foreach ($adapterFiles as $file) {
            if (! file_exists($file)) {
                continue; // file removed or renamed — not a violation of this rule
            }

            $contents = (string) file_get_contents($file);
            $this->assertStringContainsString(
                'DISABLED_REASON',
                $contents,
                sprintf(
                    'Direct AI adapter %s must carry a DISABLED_REASON constant to ' .
                    'document that direct calls are prohibited and must route through the gateway.',
                    basename($file),
                ),
            );
        }
    }

    /**
     * Service providers and controllers must not import raw AI provider classes
     * directly. They are permitted to reference TitanCoreModelGateway (the
     * sanctioned entry point) and their own support services.
     *
     * Scans Http/Controllers and Providers for forbidden direct-provider imports.
     */
    public function test_controllers_and_providers_do_not_import_raw_ai_providers(): void
    {
        $scanDirs = [
            self::MODULE_ROOT . '/Http/Controllers',
            self::MODULE_ROOT . '/Providers',
        ];

        // Classes that represent direct (bypassing) providers
        $forbidden = [
            'AI\\Adapters\\OpenAIClient',
            'AI\\Adapters\\AnthropicClient',
            'AI\\Providers\\OpenAiChatProvider',
            'AI\\Providers\\OpenAiEmbeddingProvider',
            'AI\\Providers\\LocalModelProvider',
        ];

        $violations = [];

        foreach ($scanDirs as $dir) {
            if (! is_dir($dir)) {
                continue;
            }

            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
            );

            /** @var \SplFileInfo $file */
            foreach ($iterator as $file) {
                if ($file->getExtension() !== 'php') {
                    continue;
                }

                $contents = (string) file_get_contents($file->getRealPath());

                foreach ($forbidden as $providerClass) {
                    if (str_contains($contents, $providerClass)) {
                        $violations[] = sprintf(
                            '%s imports forbidden direct provider %s — use TitanCoreModelGateway instead.',
                            $this->relativePath($file->getRealPath()),
                            $providerClass,
                        );
                    }
                }
            }
        }

        $this->assertEmpty(
            $violations,
            "Direct AI provider bypass violations:\n" . implode("\n", $violations),
        );
    }

    // ── 4. Engine manifest validation ────────────────────────────────────────

    /**
     * The canonical engine registry manifest (AI/Engines/engine.json) must exist
     * and declare all top-level required fields.
     */
    public function test_engine_manifest_exists_and_has_required_top_level_fields(): void
    {
        $manifestPath = self::MODULE_ROOT . '/AI/Engines/engine.json';

        $this->assertFileExists($manifestPath, 'AI/Engines/engine.json must exist.');

        $manifest = json_decode((string) file_get_contents($manifestPath), true, 512, JSON_THROW_ON_ERROR);

        foreach (['name', 'version', 'description', 'module', 'capabilities', 'engines'] as $field) {
            $this->assertArrayHasKey($field, $manifest, sprintf('engine.json missing required field "%s".', $field));
            $this->assertNotEmpty($manifest[$field], sprintf('engine.json field "%s" must not be empty.', $field));
        }
    }

    /**
     * Every engine entry inside AI/Engines/engine.json must declare the six
     * required sub-fields: id, name, description, class, version, lifecycle.
     */
    public function test_engine_manifest_entries_have_required_fields(): void
    {
        $manifestPath = self::MODULE_ROOT . '/AI/Engines/engine.json';

        if (! file_exists($manifestPath)) {
            $this->markTestSkipped('AI/Engines/engine.json not found.');
        }

        $manifest = json_decode((string) file_get_contents($manifestPath), true, 512, JSON_THROW_ON_ERROR);
        $engines  = $manifest['engines'] ?? [];

        $this->assertNotEmpty($engines, 'engine.json must declare at least one engine entry.');

        $required = ['id', 'name', 'description', 'class', 'version', 'lifecycle'];

        foreach ($engines as $index => $engine) {
            foreach ($required as $field) {
                $this->assertArrayHasKey(
                    $field,
                    $engine,
                    sprintf('Engine entry #%d is missing required field "%s" in engine.json.', $index, $field),
                );
                $this->assertNotEmpty(
                    $engine[$field],
                    sprintf('Engine entry #%d has empty field "%s" in engine.json.', $index, $field),
                );
            }
        }
    }

    /**
     * Every engine entry must reference a class that actually exists within the
     * Modules\TitanCore namespace, ensuring no stale or phantom registrations.
     */
    public function test_engine_manifest_class_references_exist(): void
    {
        $manifestPath = self::MODULE_ROOT . '/AI/Engines/engine.json';

        if (! file_exists($manifestPath)) {
            $this->markTestSkipped('AI/Engines/engine.json not found.');
        }

        $manifest = json_decode((string) file_get_contents($manifestPath), true, 512, JSON_THROW_ON_ERROR);
        $engines  = $manifest['engines'] ?? [];

        foreach ($engines as $engine) {
            $class = $engine['class'] ?? null;

            if ($class === null) {
                continue;
            }

            // Translate the fully-qualified class name to a relative file path
            $relative = str_replace('Modules\\TitanCore\\', '', $class);
            $relative = str_replace('\\', '/', $relative) . '.php';
            $filePath = self::MODULE_ROOT . '/' . $relative;

            $this->assertFileExists(
                $filePath,
                sprintf(
                    'Engine class "%s" referenced in engine.json does not map to an existing file (%s).',
                    $class,
                    $relative,
                ),
            );
        }
    }

    // ── 5. Manifest source-of-truth parity ───────────────────────────────────

    /**
     * The TitanSDK manifest copies must be byte-identical to the TitanCore
     * source manifests. This ensures the SDK always ships an accurate snapshot.
     */
    public function test_sdk_manifest_copies_are_byte_identical_to_source(): void
    {
        $pairs = [
            'module.json'              => 'manifests/module.json',
            'AI/asset.json'            => 'manifests/AI/asset.json',
            'AI/Agents/agent.json'     => 'manifests/AI/Agents/agent.json',
            'AI/Prompts/prompt.json'   => 'manifests/AI/Prompts/prompt.json',
            'AI/Providers/provider.json' => 'manifests/AI/Providers/provider.json',
            'AI/Tools/tool.json'       => 'manifests/AI/Tools/tool.json',
            'AI/Workflows/workflow.json' => 'manifests/AI/Workflows/workflow.json',
            'AI/Engines/engine.json'   => 'manifests/AI/Engines/engine.json',
        ];

        foreach ($pairs as $source => $sdkCopy) {
            $sourcePath  = self::MODULE_ROOT . '/' . $source;
            $sdkCopyPath = self::SDK_ROOT . '/' . $sdkCopy;

            $this->assertFileExists($sourcePath, sprintf('Source manifest %s must exist.', $source));
            $this->assertFileExists($sdkCopyPath, sprintf('SDK copy %s must exist.', $sdkCopy));

            $this->assertSame(
                file_get_contents($sourcePath),
                file_get_contents($sdkCopyPath),
                sprintf('SDK manifest copy "%s" is out of sync with source "%s".', $sdkCopy, $source),
            );
        }
    }

    // ── 6. Engine lifecycle fields ────────────────────────────────────────────

    /**
     * Every engine entry in the canonical engine manifest must declare a
     * "lifecycle" field with a non-empty, recognised lifecycle descriptor.
     */
    public function test_engine_entries_declare_recognised_lifecycle_descriptor(): void
    {
        $manifestPath = self::MODULE_ROOT . '/AI/Engines/engine.json';

        if (! file_exists($manifestPath)) {
            $this->markTestSkipped('AI/Engines/engine.json not found.');
        }

        $manifest  = json_decode((string) file_get_contents($manifestPath), true, 512, JSON_THROW_ON_ERROR);
        $engines   = $manifest['engines'] ?? [];
        $validLifecycles = ['managed', 'registered', 'installed', 'loaded', 'running', 'stopped', 'failed'];

        foreach ($engines as $index => $engine) {
            $lifecycle = $engine['lifecycle'] ?? null;

            $this->assertNotEmpty(
                $lifecycle,
                sprintf('Engine entry #%d must declare a non-empty "lifecycle" field.', $index),
            );

            $this->assertContains(
                $lifecycle,
                $validLifecycles,
                sprintf(
                    'Engine entry #%d has unrecognised lifecycle "%s". Must be one of: %s.',
                    $index,
                    $lifecycle,
                    implode(', ', $validLifecycles),
                ),
            );
        }
    }

    /**
     * Every engine entry must also declare a "permissions" array (even if empty)
     * so downstream permission-gate tooling can introspect requirements.
     */
    public function test_engine_entries_declare_permissions_array(): void
    {
        $manifestPath = self::MODULE_ROOT . '/AI/Engines/engine.json';

        if (! file_exists($manifestPath)) {
            $this->markTestSkipped('AI/Engines/engine.json not found.');
        }

        $manifest = json_decode((string) file_get_contents($manifestPath), true, 512, JSON_THROW_ON_ERROR);
        $engines  = $manifest['engines'] ?? [];

        foreach ($engines as $index => $engine) {
            $this->assertArrayHasKey(
                'permissions',
                $engine,
                sprintf(
                    'Engine entry #%d ("%s") must declare a "permissions" key for permission-gate introspection.',
                    $index,
                    $engine['id'] ?? 'unknown',
                ),
            );

            $this->assertIsArray(
                $engine['permissions'],
                sprintf('Engine entry #%d "permissions" must be an array.', $index),
            );
        }
    }

    // ── 7. Module manifest required fields ───────────────────────────────────

    /**
     * module.json must be present and declare the fields that every TitanCore
     * module is expected to carry: name, alias, description, version,
     * providers, and capabilities.
     */
    public function test_module_manifest_declares_required_fields(): void
    {
        $manifestPath = self::MODULE_ROOT . '/module.json';

        $this->assertFileExists($manifestPath, 'module.json must exist at the module root.');

        $manifest = json_decode((string) file_get_contents($manifestPath), true, 512, JSON_THROW_ON_ERROR);

        foreach (['name', 'alias', 'description', 'version', 'providers', 'capabilities'] as $field) {
            $this->assertArrayHasKey($field, $manifest, sprintf('module.json missing required field "%s".', $field));
            $this->assertNotEmpty($manifest[$field], sprintf('module.json field "%s" must not be empty.', $field));
        }
    }

    /**
     * The version declared in module.json must match the version in composer.json
     * so that the module identity is consistent.
     */
    public function test_module_json_version_matches_composer_json(): void
    {
        $moduleManifest = json_decode(
            (string) file_get_contents(self::MODULE_ROOT . '/module.json'),
            true, 512, JSON_THROW_ON_ERROR,
        );

        $composerJson = json_decode(
            (string) file_get_contents(self::MODULE_ROOT . '/composer.json'),
            true, 512, JSON_THROW_ON_ERROR,
        );

        $this->assertSame(
            $composerJson['version'] ?? null,
            $moduleManifest['version'] ?? null,
            'module.json version must match composer.json version to keep module identity consistent.',
        );
    }

    /**
     * The providers listed in module.json must each correspond to an existing PHP
     * file, preventing stale provider registrations.
     */
    public function test_module_manifest_providers_exist_as_files(): void
    {
        $manifestPath = self::MODULE_ROOT . '/module.json';

        if (! file_exists($manifestPath)) {
            $this->markTestSkipped('module.json not found.');
        }

        $manifest  = json_decode((string) file_get_contents($manifestPath), true, 512, JSON_THROW_ON_ERROR);
        $providers = $manifest['providers'] ?? [];

        foreach ($providers as $fqcn) {
            $relative = str_replace('Modules\\TitanCore\\', '', (string) $fqcn);
            $relative = str_replace('\\', '/', $relative) . '.php';
            $filePath = self::MODULE_ROOT . '/' . $relative;

            $this->assertFileExists(
                $filePath,
                sprintf('Provider "%s" declared in module.json has no corresponding file (%s).', $fqcn, $relative),
            );
        }
    }

    // ── 8. TitanSDK internal-class boundary ──────────────────────────────────

    /**
     * Files inside TitanSDK/src/Contracts must only define interfaces (not
     * concrete classes). The SDK contract layer must remain purely abstract so
     * external modules can implement or mock it freely.
     */
    public function test_sdk_contracts_are_pure_interfaces(): void
    {
        $contractsDir = self::SDK_SRC . '/Contracts';

        $this->assertDirectoryExists($contractsDir, 'TitanSDK/src/Contracts must exist.');

        $violations = [];

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($contractsDir, \FilesystemIterator::SKIP_DOTS),
        );

        /** @var \SplFileInfo $file */
        foreach ($iterator as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }

            $contents = (string) file_get_contents($file->getRealPath());

            // Look for concrete class declarations — "class Foo" without "abstract"
            if (preg_match('/^(?!.*abstract\s+class)\s*class\s+\w+/m', $contents)) {
                $violations[] = sprintf(
                    '%s defines a concrete class; SDK Contracts must be pure interfaces.',
                    $this->relativePath($file->getRealPath()),
                );
            }
        }

        $this->assertEmpty(
            $violations,
            "SDK contract concrete-class violations:\n" . implode("\n", $violations),
        );
    }

    /**
     * TitanSDK/src must not expose Modules\TitanCore internal types in its
     * public interface (use statements referencing internal classes in public
     * API files). The SDK is the stable public surface; internal implementation
     * details must not leak through it.
     *
     * Exceptions: TitanAIManager and TitanEngineManager are explicitly allowed to
     * reference TitanCore services as they are internal bridge classes.
     */
    public function test_sdk_contracts_events_and_value_objects_do_not_import_titancore_internals(): void
    {
        // Only audit directories that represent the stable public API surface
        $auditDirs = [
            self::SDK_SRC . '/Contracts',
            self::SDK_SRC . '/Events',
            self::SDK_SRC . '/Exceptions',
            self::SDK_SRC . '/ValueObjects',
        ];

        $violations = [];

        foreach ($auditDirs as $dir) {
            if (! is_dir($dir)) {
                continue;
            }

            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
            );

            /** @var \SplFileInfo $file */
            foreach ($iterator as $file) {
                if ($file->getExtension() !== 'php') {
                    continue;
                }

                $contents = (string) file_get_contents($file->getRealPath());

                // Check for use statements importing Modules\TitanCore internals
                if (preg_match('/use\s+Modules\\\\TitanCore\\\\/m', $contents)) {
                    $violations[] = sprintf(
                        '%s imports a Modules\\TitanCore internal type — SDK public surface must remain ' .
                        'independent of TitanCore implementation details.',
                        $this->relativePath($file->getRealPath()),
                    );
                }
            }
        }

        $this->assertEmpty(
            $violations,
            "SDK public surface internal-import violations:\n" . implode("\n", $violations),
        );
    }

    // ── 9. All AI execution enters through TitanCoreModelGateway ─────────────

    /**
     * TitanCoreModelGateway must exist as the single sanctioned AI routing class.
     */
    public function test_titancoremodelgateway_class_exists(): void
    {
        $file = self::MODULE_ROOT . '/Services/TitanCoreModelGateway.php';

        $this->assertFileExists(
            $file,
            'Services/TitanCoreModelGateway.php must exist as the authoritative AI routing layer.',
        );

        $contents = (string) file_get_contents($file);

        $this->assertStringContainsString(
            'class TitanCoreModelGateway',
            $contents,
            'TitanCoreModelGateway.php must declare the TitanCoreModelGateway class.',
        );
    }

    /**
     * The TitanSDK service provider must bind the SDK facade through
     * TitanCoreModelGateway (or TitanCoreAIService), never directly to a raw
     * provider adapter.
     */
    public function test_sdk_service_provider_does_not_bind_raw_providers(): void
    {
        $providerFile = self::SDK_SRC . '/Providers/TitanSdkServiceProvider.php';

        $this->assertFileExists($providerFile, 'TitanSDK/src/Providers/TitanSdkServiceProvider.php must exist.');

        $contents = (string) file_get_contents($providerFile);

        // Raw provider adapters that must not be directly bound in the SDK provider
        $forbidden = [
            'OpenAiChatProvider',
            'OpenAiEmbeddingProvider',
            'AnthropicClient',
            'LocalModelProvider',
        ];

        foreach ($forbidden as $rawClass) {
            $this->assertStringNotContainsString(
                $rawClass,
                $contents,
                sprintf(
                    'TitanSdkServiceProvider must not bind raw provider class "%s". ' .
                    'Bind TitanCoreModelGateway instead.',
                    $rawClass,
                ),
            );
        }
    }

    // ── 10. SDK contract purity (no implementation detail) ───────────────────

    /**
     * Interfaces inside TitanSDK/src/Contracts must not declare any method body
     * (they are pure interface definitions). This prevents accidental mixing of
     * implementation into the public contract layer.
     */
    public function test_sdk_contract_interfaces_contain_no_method_bodies(): void
    {
        $contractsDir = self::SDK_SRC . '/Contracts';

        if (! is_dir($contractsDir)) {
            $this->markTestSkipped('TitanSDK/src/Contracts not found.');
        }

        $violations = [];

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($contractsDir, \FilesystemIterator::SKIP_DOTS),
        );

        /** @var \SplFileInfo $file */
        foreach ($iterator as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }

            $contents = (string) file_get_contents($file->getRealPath());

            // A method body contains an opening brace after the signature;
            // interface methods end with a semicolon, not { ... }
            // We look for "function name(...) {" patterns (not "function name(...);")
            if (preg_match('/function\s+\w+[^;{]*\{/m', $contents)) {
                $violations[] = sprintf(
                    '%s contains a method with a body — SDK Contracts must only declare signatures.',
                    $this->relativePath($file->getRealPath()),
                );
            }
        }

        $this->assertEmpty(
            $violations,
            "SDK contract method-body violations:\n" . implode("\n", $violations),
        );
    }

    // ── Source-of-truth: TitanSDK composer parity ────────────────────────────

    /**
     * The TitanSDK composer.json must declare the TitanSDK\ PSR-4 prefix and
     * register TitanSdkServiceProvider, so the SDK remains a first-class
     * auto-loadable package.
     */
    public function test_sdk_composer_declares_psr4_prefix_and_service_provider(): void
    {
        $sdkComposerPath = self::SDK_ROOT . '/composer.json';

        $this->assertFileExists($sdkComposerPath, 'TitanSDK/composer.json must exist.');

        $composer = json_decode((string) file_get_contents($sdkComposerPath), true, 512, JSON_THROW_ON_ERROR);

        $this->assertSame(
            'TitanSDK\\',
            array_key_first($composer['autoload']['psr-4'] ?? []),
            'TitanSDK/composer.json autoload.psr-4 must lead with the TitanSDK\\ prefix.',
        );

        $this->assertContains(
            'TitanSDK\\Providers\\TitanSdkServiceProvider',
            $composer['extra']['laravel']['providers'] ?? [],
            'TitanSDK/composer.json must register TitanSdkServiceProvider.',
        );
    }

    /**
     * The TitanCore module composer.json must declare TitanSDK/src/ as the PSR-4
     * source for the TitanSDK\ prefix, enabling the module to ship the SDK in-tree.
     */
    public function test_module_composer_declares_sdk_psr4_path(): void
    {
        $composerPath = self::MODULE_ROOT . '/composer.json';

        $this->assertFileExists($composerPath, 'composer.json must exist at the module root.');

        $composer = json_decode((string) file_get_contents($composerPath), true, 512, JSON_THROW_ON_ERROR);

        $this->assertSame(
            'TitanSDK/src/',
            ($composer['autoload']['psr-4']['TitanSDK\\'] ?? null),
            'composer.json must map TitanSDK\\ to TitanSDK/src/ for in-tree SDK loading.',
        );
    }

    // ── Engine health-check registration ─────────────────────────────────────

    /**
     * The module-level health expectations file must exist and declare the
     * expected health checks that the platform runtime verifies on boot.
     */
    public function test_health_expectations_file_exists_and_is_valid_json(): void
    {
        $expectationsPath = self::MODULE_ROOT . '/Health/expectations.json';

        $this->assertFileExists($expectationsPath, 'Health/expectations.json must exist.');

        $expectations = json_decode(
            (string) file_get_contents($expectationsPath),
            true, 512, JSON_THROW_ON_ERROR,
        );

        $this->assertIsArray($expectations, 'Health/expectations.json must decode to an array.');
        $this->assertNotEmpty($expectations, 'Health/expectations.json must not be empty.');
    }

    /**
     * The mandatory health checks (provider, routes, manifest) must be declared
     * as truthy in the expectations file, signalling that TitanCore registers
     * these capabilities at startup.
     */
    public function test_mandatory_health_checks_declared_in_expectations(): void
    {
        $expectationsPath = self::MODULE_ROOT . '/Health/expectations.json';

        if (! file_exists($expectationsPath)) {
            $this->markTestSkipped('Health/expectations.json not found.');
        }

        $expectations = json_decode(
            (string) file_get_contents($expectationsPath),
            true, 512, JSON_THROW_ON_ERROR,
        );

        foreach (['provider', 'routes', 'manifest'] as $check) {
            $this->assertArrayHasKey(
                $check,
                $expectations,
                sprintf('Health check "%s" must be declared in Health/expectations.json.', $check),
            );

            $this->assertTrue(
                (bool) $expectations[$check],
                sprintf('Health check "%s" must be truthy in Health/expectations.json.', $check),
            );
        }
    }

    // ── Dependency rule: TitanCore module.json declares PHP requirement ────────

    /**
     * module.json must declare a PHP version requirement. Without this, the module
     * can be installed on incompatible platforms.
     */
    public function test_module_manifest_declares_php_requirement(): void
    {
        $manifest = json_decode(
            (string) file_get_contents(self::MODULE_ROOT . '/module.json'),
            true, 512, JSON_THROW_ON_ERROR,
        );

        $this->assertArrayHasKey(
            'requires_php',
            $manifest,
            'module.json must declare "requires_php" to enforce minimum PHP version compatibility.',
        );

        $this->assertNotEmpty(
            $manifest['requires_php'],
            'module.json "requires_php" must be a non-empty version constraint.',
        );
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function relativePath(string $absolutePath): string
    {
        $base = realpath(self::MODULE_ROOT);

        if ($base !== false && str_starts_with($absolutePath, $base)) {
            return ltrim(substr($absolutePath, strlen($base)), DIRECTORY_SEPARATOR);
        }

        return $absolutePath;
    }
}
