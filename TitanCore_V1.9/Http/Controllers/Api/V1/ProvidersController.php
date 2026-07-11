<?php

namespace Modules\TitanCore\Http\Controllers\Api\V1;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\TitanCore\Services\TitanCoreModelGateway;

/**
 * Provider API — /api/v1/providers/*
 *
 * Exposes AI provider metadata and operational status.
 * Never exposes secrets or credentials.
 */
class ProvidersController extends Controller
{
    public function __construct(
        private readonly TitanCoreModelGateway $gateway,
    ) {}

    /** Build the list of configured providers from platform config. */
    private function providerList(): array
    {
        $providers = [];

        // OpenAI
        $openaiKey = config('titan-ai.providers.openai.api_key') ?? config('openai.api_key');
        $providers['openai'] = [
            'id'          => 'openai',
            'name'        => 'OpenAI',
            'type'        => 'chat',
            'configured'  => ! empty($openaiKey),
            'model'       => config('titan-ai.providers.openai.model', 'gpt-4o'),
            'enabled'     => ! empty($openaiKey),
        ];

        // TitanAI
        $titanBase = config('titancore.providers.titanai.base_url') ?? config('titancore.magicai.base_url');
        $providers['titanai'] = [
            'id'          => 'titanai',
            'name'        => 'TitanAI',
            'type'        => 'gateway',
            'configured'  => ! empty($titanBase),
            'base_url'    => $titanBase ? '[configured]' : null,
            'enabled'     => (bool) config('titancore.providers.titanai.enabled', false),
        ];

        // Local model
        $localEndpoint = config('titan-ai.providers.local.endpoint');
        $providers['local'] = [
            'id'          => 'local',
            'name'        => 'Local Model',
            'type'        => 'chat',
            'configured'  => ! empty($localEndpoint),
            'endpoint'    => $localEndpoint ? '[configured]' : null,
            'enabled'     => ! empty($localEndpoint),
        ];

        return $providers;
    }

    /**
     * GET /api/v1/providers
     *
     * List all configured AI providers.
     */
    public function index(): JsonResponse
    {
        $providers = array_values($this->providerList());

        return response()->json([
            'data'  => $providers,
            'total' => count($providers),
            'ts'    => now()->toIso8601String(),
        ]);
    }

    /**
     * GET /api/v1/providers/{provider}
     *
     * Get details for a specific provider.
     */
    public function show(string $provider): JsonResponse
    {
        $list = $this->providerList();

        if (! isset($list[$provider])) {
            return response()->json(['error' => 'Provider not found'], 404);
        }

        return response()->json(['data' => $list[$provider]]);
    }

    /**
     * GET /api/v1/providers/models
     *
     * Return the list of models available per provider.
     */
    public function models(): JsonResponse
    {
        $models = [
            'openai' => [
                'gpt-4o',
                'gpt-4o-mini',
                'gpt-4-turbo',
                'gpt-3.5-turbo',
                'text-embedding-3-small',
                'text-embedding-3-large',
            ],
            'local'  => [
                config('titan-ai.providers.local.model', 'local-model'),
            ],
        ];

        return response()->json([
            'data' => $models,
            'ts'   => now()->toIso8601String(),
        ]);
    }

    /**
     * GET /api/v1/providers/health
     *
     * Return health status for all configured providers.
     */
    public function health(): JsonResponse
    {
        $checks = [];

        // OpenAI key presence check
        $openaiKey = config('titan-ai.providers.openai.api_key') ?? config('openai.api_key');
        $checks['openai'] = empty($openaiKey)
            ? ['status' => 'warning', 'message' => 'API key not configured']
            : ['status' => 'ok',      'message' => 'API key configured'];

        // TitanAI base URL check
        $titanBase = config('titancore.providers.titanai.base_url') ?? config('titancore.magicai.base_url');
        $checks['titanai'] = empty($titanBase)
            ? ['status' => 'warning', 'message' => 'Base URL not configured']
            : ['status' => 'ok',      'message' => 'Base URL configured'];

        // Local model check
        $localEndpoint = config('titan-ai.providers.local.endpoint');
        $checks['local'] = empty($localEndpoint)
            ? ['status' => 'warning', 'message' => 'Endpoint not configured']
            : ['status' => 'ok',      'message' => 'Endpoint configured'];

        $statuses = array_column($checks, 'status');
        $overall  = in_array('critical', $statuses, true) ? 'critical'
            : (in_array('warning', $statuses, true) ? 'warning' : 'ok');

        return response()->json([
            'status' => $overall,
            'checks' => $checks,
            'ts'     => now()->toIso8601String(),
        ]);
    }

    /**
     * POST /api/v1/providers/test
     *
     * Send a minimal test completion through the gateway to verify connectivity.
     */
    public function test(Request $request): JsonResponse
    {
        $provider = $request->input('provider', null);
        $options  = array_filter(['provider' => $provider]);

        try {
            $result = $this->gateway->chat(
                [['role' => 'user', 'content' => 'ping']],
                ['feature' => 'platform_provider_test'],
                $options,
            );

            return response()->json([
                'status'   => 'ok',
                'provider' => $provider ?? 'default',
                'response' => isset($result['content']) ? '[ok]' : '[no content]',
                'ts'       => now()->toIso8601String(),
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'status'  => 'error',
                'message' => $e->getMessage(),
                'ts'      => now()->toIso8601String(),
            ], 502);
        }
    }

    /**
     * GET /api/v1/providers/failover
     *
     * Return the current failover chain configuration.
     */
    public function failover(): JsonResponse
    {
        $enabled            = (bool) config('titan_model_runtime.failover.enabled', false);
        $chatProviders      = config('titan_model_runtime.failover.chat_providers', []);
        $embeddingProviders = config('titan_model_runtime.failover.embedding_providers', []);

        return response()->json([
            'enabled'             => $enabled,
            'chat_providers'      => $chatProviders,
            'embedding_providers' => $embeddingProviders,
            // Deprecated compatibility alias for older clients expecting a single chain.
            'chain'               => $chatProviders,
            'ts'                  => now()->toIso8601String(),
        ]);
    }

    /**
     * POST /api/v1/providers/benchmark
     *
     * Run a lightweight latency benchmark across configured providers.
     */
    public function benchmark(Request $request): JsonResponse
    {
        $provider = $request->input('provider', null);
        $options  = array_filter(['provider' => $provider]);
        $results  = [];

        $start = microtime(true);
        try {
            $this->gateway->chat(
                [['role' => 'user', 'content' => 'benchmark ping']],
                ['feature' => 'platform_benchmark'],
                $options,
            );
            $results[$provider ?? 'default'] = [
                'status'     => 'ok',
                'latency_ms' => (int) round((microtime(true) - $start) * 1000),
            ];
        } catch (\Throwable $e) {
            $results[$provider ?? 'default'] = [
                'status'     => 'error',
                'latency_ms' => (int) round((microtime(true) - $start) * 1000),
                'message'    => $e->getMessage(),
            ];
        }

        return response()->json([
            'results' => $results,
            'ts'      => now()->toIso8601String(),
        ]);
    }
}
