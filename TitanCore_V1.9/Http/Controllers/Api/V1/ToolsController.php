<?php

namespace Modules\TitanCore\Http\Controllers\Api\V1;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Modules\TitanCore\Services\AssetDiscoveryService;
use Modules\TitanCore\Services\TitanCoreRouter;

/**
 * Tool Runtime API — /api/v1/tools/*
 *
 * Exposes the tool lifecycle: discovery, validation, execution,
 * history, and telemetry. Reuses existing services.
 */
class ToolsController extends Controller
{
    public function __construct(
        private readonly TitanCoreRouter $router,
        private readonly AssetDiscoveryService $discovery,
    ) {}

    private function aiDir(): string
    {
        return dirname(__DIR__, 5) . DIRECTORY_SEPARATOR . 'AI';
    }

    /**
     * GET /api/v1/tools
     *
     * List all tools discovered from manifests.
     */
    public function index(): JsonResponse
    {
        $tools = $this->discovery->discoverTools($this->aiDir());

        return response()->json([
            'data'  => $tools,
            'total' => count($tools),
            'ts'    => now()->toIso8601String(),
        ]);
    }

    /**
     * GET /api/v1/tools/discover
     *
     * Re-run manifest discovery and return tool list.
     */
    public function discover(): JsonResponse
    {
        $tools = $this->discovery->discoverTools($this->aiDir());

        return response()->json([
            'data'   => $tools,
            'total'  => count($tools),
            'source' => 'filesystem',
            'ts'     => now()->toIso8601String(),
        ]);
    }

    /**
     * POST /api/v1/tools/validate
     *
     * Validate a tool invocation payload without executing it.
     */
    public function validate(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'tool'    => 'required|string|max:200',
            'input'   => 'sometimes|array',
        ]);

        $tools   = $this->discovery->discoverTools($this->aiDir());
        $toolIds = array_column($tools, 'id');

        if (! empty($toolIds) && ! in_array($validated['tool'], $toolIds, true)) {
            return response()->json([
                'valid'  => false,
                'errors' => ["Tool [{$validated['tool']}] is not registered"],
            ], 422);
        }

        return response()->json([
            'valid'   => true,
            'tool'    => $validated['tool'],
            'payload' => $validated,
            'ts'      => now()->toIso8601String(),
        ]);
    }

    /**
     * POST /api/v1/tools/execute
     *
     * Execute a tool through the router.
     */
    public function execute(Request $request): JsonResponse
    {
        $payload = $request->validate([
            'tool'    => 'required|string|max:200',
            'input'   => 'sometimes|array',
            'meta'    => 'sometimes|array',
        ]);

        $result = $this->router->invokeTool($payload);

        return response()->json($result, (int) ($result['status'] ?? 200));
    }

    /**
     * GET /api/v1/tools/history
     *
     * Return recent tool execution history from the audit log.
     */
    public function history(Request $request): JsonResponse
    {
        $limit = (int) $request->query('limit', 50);
        $rows  = [];

        try {
            if (DB::getSchemaBuilder()->hasTable('ai_runs')) {
                $rows = DB::table('ai_runs')
                    ->where('provider', 'titanai')
                    ->orderBy('created_at', 'desc')
                    ->limit($limit)
                    ->get(['id', 'company_id', 'user_id', 'action', 'model', 'status', 'created_at'])
                    ->toArray();
            }
        } catch (\Throwable) {}

        return response()->json([
            'data'  => $rows,
            'total' => count($rows),
            'ts'    => now()->toIso8601String(),
        ]);
    }

    /**
     * GET /api/v1/tools/telemetry
     *
     * Return tool usage telemetry aggregates.
     */
    public function telemetry(Request $request): JsonResponse
    {
        $aggregate = ['total_runs' => 0, 'success' => 0, 'error' => 0];

        try {
            if (DB::getSchemaBuilder()->hasTable('ai_runs')) {
                $rows = DB::table('ai_runs')
                    ->selectRaw('status, COUNT(*) as cnt')
                    ->groupBy('status')
                    ->get();

                foreach ($rows as $row) {
                    $aggregate['total_runs'] += (int) $row->cnt;
                    if ($row->status === 'success') {
                        $aggregate['success'] = (int) $row->cnt;
                    } elseif ($row->status === 'error') {
                        $aggregate['error'] = (int) $row->cnt;
                    }
                }
            }
        } catch (\Throwable) {}

        return response()->json([
            'telemetry' => $aggregate,
            'ts'        => now()->toIso8601String(),
        ]);
    }
}
