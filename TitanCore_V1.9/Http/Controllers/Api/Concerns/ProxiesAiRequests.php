<?php

namespace Modules\TitanCore\Http\Controllers\Api\Concerns;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\TitanCore\Services\TitanCoreRouter;

/**
 * Shared proxy logic for AI passthrough controllers.
 *
 * Provides the canonical proxy() and ping() implementations used by both
 * TitanAiProxyController and MagicAiProxyController, preserving
 * their distinct authorization contexts while eliminating code duplication.
 */
trait ProxiesAiRequests
{
    /**
     * Proxy any HTTP method + path to the upstream AI service.
     *
     * Usage:
     *  - /api/titancore/{prefix}/proxy?path=/api/chat/send-message
     *  - /api/titancore/{prefix}/proxy/{any} where {any} becomes /{any}
     */
    public function proxy(Request $request, TitanCoreRouter $router, ?string $any = null): JsonResponse
    {
        $method = strtoupper($request->method());

        // Allow path via query (?path=/api/...) or via wildcard segment (/proxy/api/chat/...)
        $path = $request->query('path');
        if (!$path && $any) {
            $path = '/' . ltrim($any, '/');
        }

        if (!$path) {
            return response()->json([
                'ok'     => false,
                'status' => 422,
                'body'   => ['error' => 'Missing path. Provide ?path=/api/... or /proxy/{path}'],
            ], 422);
        }

        // Forward request payload. Prefer JSON; fallback to all() for form posts.
        $payload = $request->isJson()
            ? (array) $request->json()->all()
            : (array) $request->all();

        // Forward select headers
        $forwardHeaders = [];
        foreach (['Accept', 'Content-Type'] as $header) {
            if ($request->headers->has($header)) {
                $forwardHeaders[$header] = $request->headers->get($header);
            }
        }

        $result = $router->invokeTool([
            'method'  => $method,
            'path'    => $path,
            'payload' => $payload,
            'headers' => $forwardHeaders,
        ]);

        return response()->json($result, $result['status'] ?? 200);
    }

    /**
     * Probe the upstream AI service health endpoint.
     */
    public function ping(Request $request, TitanCoreRouter $router): JsonResponse
    {
        $paths = ['/api/health', '/api/status', '/v1/health', '/health'];

        foreach ($paths as $path) {
            $res = $router->invokeTool(['method' => 'GET', 'path' => $path, 'payload' => []]);

            if (($res['ok'] ?? false) || (($res['status'] ?? 0) > 0 && ($res['status'] ?? 0) < 500)) {
                return response()->json([
                    'ok'     => true,
                    'status' => $res['status'] ?? 200,
                    'body'   => $res['body'] ?? null,
                    'path'   => $path,
                ], 200);
            }
        }

        return response()->json([
            'ok'     => false,
            'status' => 502,
            'body'   => ['error' => 'No health endpoint responded'],
        ], 502);
    }
}
