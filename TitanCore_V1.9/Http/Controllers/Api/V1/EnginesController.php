<?php

namespace Modules\TitanCore\Http\Controllers\Api\V1;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\TitanCore\Services\Engine\EngineManager;

class EnginesController extends Controller
{
    public function __construct(
        private readonly EngineManager $engines,
    ) {}

    private function moduleDir(): string
    {
        return dirname(__DIR__, 5);
    }

    public function index(): JsonResponse
    {
        return response()->json([
            'data'  => $this->engines->all(),
            'total' => count($this->engines->all()),
            'ts'    => now()->toIso8601String(),
        ]);
    }

    public function discover(): JsonResponse
    {
        $items = $this->engines->discover($this->moduleDir());

        return response()->json([
            'data'  => $items,
            'total' => count($items),
            'ts'    => now()->toIso8601String(),
        ]);
    }

    public function validateEngine(Request $request): JsonResponse
    {
        $result = $this->engines->validate((string) $request->input('engine_id', ''));

        return response()->json([
            'data' => $result,
            'ts'   => now()->toIso8601String(),
        ], $result['valid'] ? 200 : 422);
    }

    public function install(Request $request): JsonResponse
    {
        $engine = $this->engines->install((string) $request->input('engine_id', ''));

        if ($engine === null) {
            return response()->json(['error' => 'Engine not found'], 404);
        }

        return response()->json(['data' => $engine, 'ts' => now()->toIso8601String()]);
    }

    public function load(Request $request): JsonResponse
    {
        $engine = $this->engines->load((string) $request->input('engine_id', ''));

        if ($engine === null) {
            return response()->json(['error' => 'Engine not found'], 404);
        }

        return response()->json(['data' => $engine, 'ts' => now()->toIso8601String()]);
    }

    public function lifecycle(Request $request): JsonResponse
    {
        $engine = $this->engines->lifecycle(
            (string) $request->input('engine_id', ''),
            (string) $request->input('state', ''),
        );

        if ($engine === null) {
            return response()->json(['error' => 'Engine not found'], 404);
        }

        return response()->json(['data' => $engine, 'ts' => now()->toIso8601String()]);
    }
}
