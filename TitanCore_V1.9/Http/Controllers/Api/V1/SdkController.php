<?php

namespace Modules\TitanCore\Http\Controllers\Api\V1;

use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;

/**
 * SDK Metadata API — /api/v1/sdk/*
 *
 * Describes the public integration surface of TitanCore for external
 * module developers. Exposes contracts, events, manifests, version,
 * and capabilities — not internal implementation classes.
 */
class SdkController extends Controller
{
    // Http/Controllers/Api/V1 sits five levels below the TitanCore module root.
    private const MODULE_ROOT_DEPTH = 5;
    private const SDK_DIRECTORY = 'TitanSDK';

    private function moduleBasePath(): string
    {
        // This controller lives in Http/Controllers/Api/V1, five levels below the module root.
        return dirname(__DIR__, self::MODULE_ROOT_DEPTH);
    }

    private function sdkBasePath(): string
    {
        return $this->moduleBasePath() . DIRECTORY_SEPARATOR . self::SDK_DIRECTORY;
    }

    private function manifestPrefix(): string
    {
        if (is_dir($this->sdkBasePath() . DIRECTORY_SEPARATOR . 'manifests' . DIRECTORY_SEPARATOR . 'AI')) {
            return 'TitanSDK/manifests/AI/';
        }

        return 'AI/';
    }

    private function moduleManifestPath(): string
    {
        if (is_dir($this->sdkBasePath() . DIRECTORY_SEPARATOR . 'manifests' . DIRECTORY_SEPARATOR . 'AI')) {
            return 'TitanSDK/manifests/module.json';
        }

        return 'module.json';
    }

    private function moduleMeta(): array
    {
        $base       = $this->moduleBasePath();
        $version    = null;
        $moduleJson = [];

        try {
            $vFile = $base . DIRECTORY_SEPARATOR . 'version.txt';
            if (is_file($vFile)) {
                $version = trim((string) file_get_contents($vFile));
            }
        } catch (\Throwable) {}

        try {
            $mFile = $base . DIRECTORY_SEPARATOR . 'module.json';
            if (is_file($mFile)) {
                $moduleJson = json_decode((string) file_get_contents($mFile), true) ?: [];
            }
        } catch (\Throwable) {}

        return ['version' => $version, 'module' => $moduleJson];
    }

    /**
     * @return list<string>
     */
    private function listPhpSymbols(string $directory, string $namespace): array
    {
        $symbols = [];

        if (! is_dir($directory)) {
            return $symbols;
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS),
        );

        foreach ($iterator as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }

            $relative = str_replace($directory . DIRECTORY_SEPARATOR, '', $file->getPathname());
            $name = str_replace([DIRECTORY_SEPARATOR, '.php'], ['\\', ''], $relative);
            $symbols[] = $namespace . '\\' . $name;
        }

        sort($symbols);

        return $symbols;
    }

    /**
     * GET /api/v1/sdk/contracts
     *
     * List all public contract interfaces exposed by TitanCore.
     */
    public function contracts(): JsonResponse
    {
        $contractsDir = $this->sdkBasePath() . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR . 'Contracts';
        $namespace = 'TitanSDK\\Contracts';

        if (! is_dir($contractsDir)) {
            $contractsDir = $this->moduleBasePath() . DIRECTORY_SEPARATOR . 'Contracts';
            $namespace = 'Modules\\TitanCore\\Contracts';
        }

        $contracts = $this->listPhpSymbols($contractsDir, $namespace);

        return response()->json([
            'contracts' => $contracts,
            'total'     => count($contracts),
            'ts'        => now()->toIso8601String(),
        ]);
    }

    /**
     * GET /api/v1/sdk/events
     *
     * List all public domain events emitted by TitanCore.
     */
    public function events(): JsonResponse
    {
        $eventsDir = $this->sdkBasePath() . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR . 'Events';
        $namespace = 'TitanSDK\\Events';

        if (! is_dir($eventsDir)) {
            $eventsDir = $this->moduleBasePath() . DIRECTORY_SEPARATOR . 'Events';
            $namespace = 'Modules\\TitanCore\\Events';
        }

        $events = $this->listPhpSymbols($eventsDir, $namespace);

        return response()->json([
            'events' => $events,
            'total'  => count($events),
            'ts'     => now()->toIso8601String(),
        ]);
    }

    /**
     * GET /api/v1/sdk/manifests
     *
     * List manifest schema types supported by TitanCore.
     */
    public function manifests(): JsonResponse
    {
        $prefix = $this->manifestPrefix();

        $schemas = [
            [
                'type'        => 'module',
                'file'        => $this->moduleManifestPath(),
                'description' => 'Module metadata manifest',
            ],
            [
                'type'        => 'asset',
                'file'        => $prefix . 'asset.json',
                'description' => 'Master AI subsystem manifest listing exported assets',
            ],
            [
                'type'        => 'provider',
                'file'        => $prefix . 'Providers/provider.json',
                'description' => 'AI provider registration manifest',
            ],
            [
                'type'        => 'agent',
                'file'        => $prefix . 'Agents/agent.json',
                'description' => 'Agent orchestration manifest',
            ],
            [
                'type'        => 'tool',
                'file'        => $prefix . 'Tools/tool.json',
                'description' => 'Tool registration manifest',
            ],
            [
                'type'        => 'prompt',
                'file'        => $prefix . 'Prompts/prompt.json',
                'description' => 'Prompt template manifest',
            ],
            [
                'type'        => 'workflow',
                'file'        => $prefix . 'Workflows/workflow.json',
                'description' => 'Workflow orchestration manifest',
            ],
        ];

        return response()->json([
            'schemas' => $schemas,
            'total'   => count($schemas),
            'ts'      => now()->toIso8601String(),
        ]);
    }

    /**
     * GET /api/v1/sdk/version
     *
     * Return SDK/platform version information.
     */
    public function version(): JsonResponse
    {
        $meta = $this->moduleMeta();

        return response()->json([
            'sdk_version'    => $meta['version'] ?? 'unknown',
            'platform'       => 'TitanCore',
            'php'            => PHP_VERSION,
            'laravel'        => app()->version(),
            'stability'      => 'stable',
            'ts'             => now()->toIso8601String(),
        ]);
    }

    /**
     * GET /api/v1/sdk/capabilities
     *
     * Return all capabilities exposed through the public SDK.
     */
    public function capabilities(): JsonResponse
    {
        $meta         = $this->moduleMeta();
        $capabilities = $meta['module']['capabilities'] ?? [];

        $surface = [
            'chat'       => in_array('chat', $capabilities, true),
            'embeddings' => in_array('embeddings', $capabilities, true),
            'rag'        => in_array('rag', $capabilities, true),
            'tools'      => in_array('function-calling', $capabilities, true),
            'streaming'  => in_array('streaming', $capabilities, true),
            'workflows'  => in_array('workflow', $capabilities, true),
            'knowledge'  => in_array('knowledge-base', $capabilities, true),
        ];

        return response()->json([
            'capabilities'  => $capabilities,
            'surface'       => $surface,
            'ts'            => now()->toIso8601String(),
        ]);
    }
}
