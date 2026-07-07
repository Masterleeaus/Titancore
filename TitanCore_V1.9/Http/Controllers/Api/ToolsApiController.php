<?php

namespace Modules\TitanCore\Http\Controllers\Api;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\TitanCore\Services\TitanCoreRouter;

class ToolsApiController extends Controller
{
    public function invoke(Request $request, TitanCoreRouter $router)
    {
        $payload = $request->validate([
            // Option A: opinionated tool invoke
            'tool' => 'sometimes|string|max:200',
            'input' => 'sometimes|array',

            // Option B: proxy passthrough
            'method' => 'sometimes|string|max:10',
            'path' => 'sometimes|string|max:255',
            'payload' => 'sometimes|array',

            // Optional metadata
            'meta' => 'sometimes|array',
        ]);

        $result = $router->invokeTool($payload);

        return response()->json($result, $result['status'] ?? 200);
    }
}
