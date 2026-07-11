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
    private function moduleMeta(): array
    {
        $base       = dirname(__DIR__, 5);
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
     * GET /api/v1/sdk/contracts
     *
     * List all public contract interfaces exposed by TitanCore.
     */
    public function contracts(): JsonResponse
    {
        $contractsDir = dirname(__DIR__, 5) . DIRECTORY_SEPARATOR . 'Contracts';
        $contracts    = [];

        if (is_dir($contractsDir)) {
            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($contractsDir, \FilesystemIterator::SKIP_DOTS),
            );

            foreach ($iterator as $file) {
                if ($file->getExtension() !== 'php') {
                    continue;
                }

                $relative   = str_replace($contractsDir . DIRECTORY_SEPARATOR, '', $file->getPathname());
                $interface  = str_replace([DIRECTORY_SEPARATOR, '.php'], ['\\', ''], $relative);
                $contracts[] = 'Modules\\TitanCore\\Contracts\\' . $interface;
            }
        }

        sort($contracts);

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
        $eventsDir = dirname(__DIR__, 5) . DIRECTORY_SEPARATOR . 'Events';
        $events    = [];

        if (is_dir($eventsDir)) {
            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($eventsDir, \FilesystemIterator::SKIP_DOTS),
            );

            foreach ($iterator as $file) {
                if ($file->getExtension() !== 'php') {
                    continue;
                }

                $relative = str_replace($eventsDir . DIRECTORY_SEPARATOR, '', $file->getPathname());
                $event    = str_replace([DIRECTORY_SEPARATOR, '.php'], ['\\', ''], $relative);
                $events[] = 'Modules\\TitanCore\\Events\\' . $event;
            }
        }

        sort($events);

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
        $schemas = [
            [
                'type'        => 'asset',
                'file'        => 'AI/asset.json',
                'description' => 'Master AI subsystem manifest listing exported assets',
            ],
            [
                'type'        => 'provider',
                'file'        => 'AI/Providers/provider.json',
                'description' => 'AI provider registration manifest',
            ],
            [
                'type'        => 'agent',
                'file'        => 'AI/Agents/agent.json',
                'description' => 'Agent orchestration manifest',
            ],
            [
                'type'        => 'tool',
                'file'        => 'AI/Tools/tool.json',
                'description' => 'Tool registration manifest',
            ],
            [
                'type'        => 'prompt',
                'file'        => 'AI/Prompts/prompt.json',
                'description' => 'Prompt template manifest',
            ],
            [
                'type'        => 'workflow',
                'file'        => 'AI/Workflows/workflow.json',
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
