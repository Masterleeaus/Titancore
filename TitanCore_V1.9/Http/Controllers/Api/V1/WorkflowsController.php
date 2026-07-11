<?php

namespace Modules\TitanCore\Http\Controllers\Api\V1;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Modules\TitanCore\Services\AssetDiscoveryService;

/**
 * Workflow API — /api/v1/workflows/*
 *
 * Exposes workflow orchestration metadata and execution control.
 * Workflow coordinates execution — it does not duplicate runtime behaviour.
 */
class WorkflowsController extends Controller
{
    public function __construct(
        private readonly AssetDiscoveryService $discovery,
    ) {}

    private function aiDir(): string
    {
        return dirname(__DIR__, 5) . DIRECTORY_SEPARATOR . 'AI';
    }

    private function runsTableExists(): bool
    {
        try {
            return DB::getSchemaBuilder()->hasTable('ai_workflow_runs');
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * GET /api/v1/workflows
     *
     * List all workflows discovered from manifests.
     */
    public function index(): JsonResponse
    {
        $workflows = $this->discovery->discoverWorkflows($this->aiDir());

        return response()->json([
            'data'  => $workflows,
            'total' => count($workflows),
            'ts'    => now()->toIso8601String(),
        ]);
    }

    /**
     * POST /api/v1/workflows/run
     *
     * Enqueue or start a workflow run.
     */
    public function run(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'workflow'  => 'required|string|max:255',
            'input'     => 'sometimes|array',
            'context'   => 'sometimes|array',
        ]);

        $runId = null;
        if ($this->runsTableExists()) {
            $runId = DB::table('ai_workflow_runs')->insertGetId([
                'workflow'   => $validated['workflow'],
                'status'     => 'queued',
                'input'      => json_encode($validated['input'] ?? []),
                'user_id'    => optional($request->user())->id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        return response()->json([
            'status'      => 'queued',
            'workflow'    => $validated['workflow'],
            'run_id'      => $runId,
            'ts'          => now()->toIso8601String(),
        ], 202);
    }

    /**
     * GET /api/v1/workflows/status
     *
     * Get the status of a workflow run.
     */
    public function status(Request $request): JsonResponse
    {
        $runId = $request->query('run_id');
        if (! $runId) {
            return response()->json(['error' => 'run_id required'], 422);
        }

        if (! $this->runsTableExists()) {
            return response()->json(['error' => 'Workflow run tracking not available'], 503);
        }

        $run = DB::table('ai_workflow_runs')->where('id', $runId)->first();
        if (! $run) {
            return response()->json(['error' => 'Run not found'], 404);
        }

        return response()->json(['data' => $run]);
    }

    /**
     * POST /api/v1/workflows/pause
     *
     * Pause a running workflow.
     */
    public function pause(Request $request): JsonResponse
    {
        $runId = $request->input('run_id');
        if (! $runId) {
            return response()->json(['error' => 'run_id required'], 422);
        }

        if ($this->runsTableExists()) {
            DB::table('ai_workflow_runs')->where('id', $runId)->update([
                'status'     => 'paused',
                'updated_at' => now(),
            ]);
        }

        return response()->json(['status' => 'paused', 'run_id' => $runId, 'ts' => now()->toIso8601String()]);
    }

    /**
     * POST /api/v1/workflows/resume
     *
     * Resume a paused workflow.
     */
    public function resume(Request $request): JsonResponse
    {
        $runId = $request->input('run_id');
        if (! $runId) {
            return response()->json(['error' => 'run_id required'], 422);
        }

        if ($this->runsTableExists()) {
            DB::table('ai_workflow_runs')->where('id', $runId)->update([
                'status'     => 'running',
                'updated_at' => now(),
            ]);
        }

        return response()->json(['status' => 'running', 'run_id' => $runId, 'ts' => now()->toIso8601String()]);
    }

    /**
     * POST /api/v1/workflows/cancel
     *
     * Cancel a workflow run.
     */
    public function cancel(Request $request): JsonResponse
    {
        $runId = $request->input('run_id');
        if (! $runId) {
            return response()->json(['error' => 'run_id required'], 422);
        }

        if ($this->runsTableExists()) {
            DB::table('ai_workflow_runs')->where('id', $runId)->update([
                'status'     => 'cancelled',
                'updated_at' => now(),
            ]);
        }

        return response()->json(['status' => 'cancelled', 'run_id' => $runId, 'ts' => now()->toIso8601String()]);
    }

    /**
     * POST /api/v1/workflows/replay
     *
     * Replay a completed or failed workflow run.
     */
    public function replay(Request $request): JsonResponse
    {
        $runId = $request->input('run_id');
        if (! $runId) {
            return response()->json(['error' => 'run_id required'], 422);
        }

        $newRunId = null;
        if ($this->runsTableExists()) {
            $original = DB::table('ai_workflow_runs')->where('id', $runId)->first();
            if (! $original) {
                return response()->json(['error' => 'Original run not found'], 404);
            }

            $newRunId = DB::table('ai_workflow_runs')->insertGetId([
                'workflow'      => $original->workflow,
                'status'        => 'queued',
                'input'         => $original->input,
                'replayed_from' => $runId,
                'user_id'       => optional($request->user())->id,
                'created_at'    => now(),
                'updated_at'    => now(),
            ]);
        }

        return response()->json([
            'status'        => 'queued',
            'run_id'        => $newRunId,
            'replayed_from' => $runId,
            'ts'            => now()->toIso8601String(),
        ], 202);
    }

    /**
     * GET /api/v1/workflows/history
     *
     * Return recent workflow run history.
     */
    public function history(Request $request): JsonResponse
    {
        $limit = (int) $request->query('limit', 50);
        $rows  = [];

        if ($this->runsTableExists()) {
            $rows = DB::table('ai_workflow_runs')
                ->orderBy('created_at', 'desc')
                ->limit($limit)
                ->get()
                ->toArray();
        }

        return response()->json([
            'data'  => $rows,
            'total' => count($rows),
            'ts'    => now()->toIso8601String(),
        ]);
    }
}
