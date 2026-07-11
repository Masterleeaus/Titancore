<?php

namespace Modules\TitanCore\Http\Controllers\Api;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\TitanCore\Services\TitanCoreRouter;

/**
 * titanai Proxy Controller
 *
 * Exposes titanai endpoints through TitanCore so Worksuite can use all titanai features.
 * This is a raw passthrough gateway (still permission-gated at the route level).
 */
class TitanAiProxyController extends Controller
{
    /**
     * Proxy any HTTP method + path to titanai.
     *
     * Usage:
     *  - /api/titancore/titanai/proxy?path=/api/chat/send-message
     *  - /api/titancore/titanai/proxy/{any} where {any} becomes /{any}
     */
    public function proxy(Request $request, TitanCoreRouter $router, ?string $any = null)
    {
        $method = strtoupper($request->method());

        // Allow path via query (?path=/api/...) or via wildcard segment (/proxy/api/chat/...)
        $path = $request->query('path');
        if (!$path && $any) {
            $path = '/' . ltrim($any, '/');
        }

        if (!$path) {
            return response()->json([
                'ok' => false,
                'status' => 422,
                'body' => ['error' => 'Missing path. Provide ?path=/api/... or /proxy/{path}'],
            ], 422);
        }

        // Forward request payload. Prefer JSON; fallback to all() for form posts.
        $payload = [];
        if ($request->isJson()) {
            $payload = (array) $request->json()->all();
        } else {
            $payload = (array) $request->all();
        }

        // Forward select headers (optional)
        $forwardHeaders = [];
        foreach (['Accept', 'Content-Type'] as $h) {
            if ($request->headers->has($h)) {
                $forwardHeaders[$h] = $request->headers->get($h);
            }
        }

        $result = $router->invokeTool([
            'method' => $method,
            'path' => $path,
            'payload' => $payload,
            'headers' => $forwardHeaders,
        ]);

        return response()->json($result, $result['status'] ?? 200);
    }

    public function ping(Request $request, TitanCoreRouter $router)
    {
        // Try common health endpoints; you can change later
        $paths = ['/api/health', '/api/status', '/v1/health', '/health'];
        foreach ($paths as $p) {
            $res = $router->invokeTool(['method' => 'GET', 'path' => $p, 'payload' => []]);
            if (($res['ok'] ?? false) || (($res['status'] ?? 0) && ($res['status'] ?? 0) < 500)) {
                return response()->json(['ok' => true, 'status' => $res['status'] ?? 200, 'body' => $res['body'] ?? null, 'path' => $p], 200);
            }
        }
        return response()->json(['ok' => false, 'status' => 502, 'body' => ['error' => 'No health endpoint responded']], 502);
    }
}
