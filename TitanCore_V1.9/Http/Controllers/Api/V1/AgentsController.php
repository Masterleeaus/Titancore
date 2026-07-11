<?php

namespace Modules\TitanCore\Http\Controllers\Api\V1;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Modules\TitanCore\Services\AssetDiscoveryService;
use Modules\TitanCore\Services\TitanCoreModelGateway;

/**
 * Agent API — /api/v1/agents/*
 *
 * Exposes the agent runtime: listing, running, status tracking,
 * history, goals, plans, and results.
 *
 * Reuses existing orchestration services.
 */
class AgentsController extends Controller
{
    public function __construct(
        private readonly AssetDiscoveryService $discovery,
        private readonly TitanCoreModelGateway $gateway,
    ) {}

    private function aiDir(): string
    {
        return dirname(__DIR__, 5) . DIRECTORY_SEPARATOR . 'AI';
    }

    private function runsTableExists(): bool
    {
        try {
            return DB::getSchemaBuilder()->hasTable('ai_agent_runs');
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * GET /api/v1/agents
     *
     * List all agents discovered from manifests.
     */
    public function index(): JsonResponse
    {
        $agents = $this->discovery->discoverAgents($this->aiDir());

        return response()->json([
            'data'  => $agents,
            'total' => count($agents),
            'ts'    => now()->toIso8601String(),
        ]);
    }

    /**
     * POST /api/v1/agents/run
     *
     * Start an agent run.
     */
    public function run(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'agent'   => 'required|string|max:255',
            'input'   => 'sometimes|array',
            'context' => 'sometimes|array',
        ]);

        $runId = null;
        if ($this->runsTableExists()) {
            $runId = DB::table('ai_agent_runs')->insertGetId([
                'agent'      => $validated['agent'],
                'status'     => 'queued',
                'input'      => json_encode($validated['input'] ?? []),
                'user_id'    => optional($request->user())->id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        return response()->json([
            'status'  => 'queued',
            'agent'   => $validated['agent'],
            'run_id'  => $runId,
            'ts'      => now()->toIso8601String(),
        ], 202);
    }

    /**
     * GET /api/v1/agents/status
     *
     * Get the status of an agent run.
     */
    public function status(Request $request): JsonResponse
    {
        $runId = $request->query('run_id');
        if (! $runId) {
            return response()->json(['error' => 'run_id required'], 422);
        }

        if (! $this->runsTableExists()) {
            return response()->json(['error' => 'Agent run tracking not available'], 503);
        }

        $run = DB::table('ai_agent_runs')->where('id', $runId)->first();
        if (! $run) {
            return response()->json(['error' => 'Run not found'], 404);
        }

        return response()->json(['data' => $run]);
    }

    /**
     * GET /api/v1/agents/history
     *
     * Return recent agent run history.
     */
    public function history(Request $request): JsonResponse
    {
        $limit = (int) $request->query('limit', 50);
        $rows  = [];

        if ($this->runsTableExists()) {
            $rows = DB::table('ai_agent_runs')
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

    /**
     * GET /api/v1/agents/goals
     *
     * Return goals for an agent run.
     */
    public function goals(Request $request): JsonResponse
    {
        $runId = $request->query('run_id');
        if (! $runId) {
            return response()->json(['error' => 'run_id required'], 422);
        }

        $goals = [];
        if (DB::getSchemaBuilder()->hasTable('ai_agent_goals')) {
            $goals = DB::table('ai_agent_goals')
                ->where('run_id', $runId)
                ->orderBy('created_at')
                ->get()
                ->toArray();
        }

        return response()->json(['data' => $goals, 'run_id' => $runId]);
    }

    /**
     * GET /api/v1/agents/plans
     *
     * Return execution plan for an agent run.
     */
    public function plans(Request $request): JsonResponse
    {
        $runId = $request->query('run_id');
        if (! $runId) {
            return response()->json(['error' => 'run_id required'], 422);
        }

        $plans = [];
        if (DB::getSchemaBuilder()->hasTable('ai_agent_plans')) {
            $plans = DB::table('ai_agent_plans')
                ->where('run_id', $runId)
                ->orderBy('step_index')
                ->get()
                ->toArray();
        }

        return response()->json(['data' => $plans, 'run_id' => $runId]);
    }

    /**
     * GET /api/v1/agents/results
     *
     * Return the results for an agent run.
     */
    public function results(Request $request): JsonResponse
    {
        $runId = $request->query('run_id');
        if (! $runId) {
            return response()->json(['error' => 'run_id required'], 422);
        }

        if (! $this->runsTableExists()) {
            return response()->json(['error' => 'Agent run tracking not available'], 503);
        }

        $run = DB::table('ai_agent_runs')->where('id', $runId)->first();
        if (! $run) {
            return response()->json(['error' => 'Run not found'], 404);
        }

        $output = null;
        if (isset($run->output)) {
            $output = is_string($run->output) ? json_decode($run->output, true) : $run->output;
        }

        return response()->json([
            'run_id'  => $runId,
            'status'  => $run->status ?? null,
            'results' => $output,
            'ts'      => now()->toIso8601String(),
        ]);
    }
}
