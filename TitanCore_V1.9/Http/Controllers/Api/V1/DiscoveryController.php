<?php

namespace Modules\TitanCore\Http\Controllers\Api\V1;

use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Modules\TitanCore\Services\AssetDiscoveryService;

/**
 * Discovery API — /api/v1/discovery/*
 *
 * Exposes metadata-driven asset discovery using the existing
 * AssetDiscoveryService. Does not duplicate discovery logic.
 */
class DiscoveryController extends Controller
{
    public function __construct(
        private readonly AssetDiscoveryService $discovery,
    ) {}

    private function aiDir(): string
    {
        return dirname(__DIR__, 5) . DIRECTORY_SEPARATOR . 'AI';
    }

    /**
     * GET /api/v1/discovery/assets
     *
     * Discover all platform assets from manifest files.
     */
    public function assets(): JsonResponse
    {
        $discovered = $this->discovery->discoverFromDirectory($this->aiDir());

        return response()->json([
            'data' => $discovered,
            'ts'   => now()->toIso8601String(),
        ]);
    }

    /**
     * GET /api/v1/discovery/providers
     *
     * Discover all registered AI providers from manifests.
     */
    public function providers(): JsonResponse
    {
        $items = $this->discovery->discoverProviders($this->aiDir());

        return response()->json([
            'data'  => $items,
            'total' => count($items),
            'ts'    => now()->toIso8601String(),
        ]);
    }

    /**
     * GET /api/v1/discovery/tools
     *
     * Discover all registered tools from manifests.
     */
    public function tools(): JsonResponse
    {
        $items = $this->discovery->discoverTools($this->aiDir());

        return response()->json([
            'data'  => $items,
            'total' => count($items),
            'ts'    => now()->toIso8601String(),
        ]);
    }

    /**
     * GET /api/v1/discovery/workflows
     *
     * Discover all registered workflows from manifests.
     */
    public function workflows(): JsonResponse
    {
        $items = $this->discovery->discoverWorkflows($this->aiDir());

        return response()->json([
            'data'  => $items,
            'total' => count($items),
            'ts'    => now()->toIso8601String(),
        ]);
    }

    /**
     * GET /api/v1/discovery/prompts
     *
     * Discover all registered prompts from manifests.
     */
    public function prompts(): JsonResponse
    {
        $items = $this->discovery->discoverPrompts($this->aiDir());

        return response()->json([
            'data'  => $items,
            'total' => count($items),
            'ts'    => now()->toIso8601String(),
        ]);
    }

    /**
     * GET /api/v1/discovery/agents
     *
     * Discover all registered agents from manifests.
     */
    public function agents(): JsonResponse
    {
        $items = $this->discovery->discoverAgents($this->aiDir());

        return response()->json([
            'data'  => $items,
            'total' => count($items),
            'ts'    => now()->toIso8601String(),
        ]);
    }

    /**
     * GET /api/v1/discovery/engines
     *
     * Discover all registered engines from manifests.
     */
    public function engines(): JsonResponse
    {
        $items = $this->discovery->discoverEngines($this->aiDir());

        return response()->json([
            'data'  => $items,
            'total' => count($items),
            'ts'    => now()->toIso8601String(),
        ]);
    }

    /**
     * GET /api/v1/discovery/manifests
     *
     * Return all discovered manifests with capability index.
     */
    public function manifests(): JsonResponse
    {
        $discovered   = $this->discovery->discoverFromDirectory($this->aiDir());
        $capabilities = $this->discovery->indexCapabilities($discovered);

        return response()->json([
            'manifests'    => [
                'asset'     => $discovered['asset'],
                'providers' => $discovered['providers'],
                'agents'    => $discovered['agents'],
                'tools'     => $discovered['tools'],
                'prompts'   => $discovered['prompts'],
                'workflows' => $discovered['workflows'],
                'engines'   => $discovered['engines'],
            ],
            'capabilities' => $capabilities,
            'errors'       => $discovered['errors'],
            'ts'           => now()->toIso8601String(),
        ]);
    }
}
