<?php
namespace Modules\TitanCore\Services;

use Illuminate\Support\Facades\DB;

class TitanAIRunLogService
{
    public function create(string $type, ?int $tenantId = null, bool $embed = false, array $meta = []): ?int
    {
        if (!DB::getSchemaBuilder()->hasTable('titan_ai_runs')) return null;

        return (int) DB::table('titan_ai_runs')->insertGetId([
            'tenant_id' => $tenantId,
            'run_type' => $type,
            'status' => 'queued',
            'embed' => $embed ? 1 : 0,
            'meta' => json_encode($meta),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function start(?int $runId): void
    {
        if (!$runId) return;
        if (!DB::getSchemaBuilder()->hasTable('titan_ai_runs')) return;

        DB::table('titan_ai_runs')->where('id', $runId)->update([
            'status' => 'running',
            'started_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function success(?int $runId, array $stats = [], ?string $message = null): void
    {
        if (!$runId) return;
        if (!DB::getSchemaBuilder()->hasTable('titan_ai_runs')) return;

        DB::table('titan_ai_runs')->where('id', $runId)->update([
            'status' => 'success',
            'documents' => (int)($stats['documents'] ?? 0),
            'chunks' => (int)($stats['chunks'] ?? 0),
            'message' => $message,
            'finished_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function failed(?int $runId, string $message, array $meta = []): void
    {
        if (!$runId) return;
        if (!DB::getSchemaBuilder()->hasTable('titan_ai_runs')) return;

        DB::table('titan_ai_runs')->where('id', $runId)->update([
            'status' => 'failed',
            'message' => $message,
            'meta' => json_encode($meta),
            'finished_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function latest(?int $tenantId = null, int $limit = 10): array
    {
        if (!DB::getSchemaBuilder()->hasTable('titan_ai_runs')) return [];
        $q = DB::table('titan_ai_runs')->orderByDesc('id')->limit($limit);
        if ($tenantId !== null) {
            // show tenant-specific runs first + global as fallback
            $rows = (clone $q)->where('tenant_id', $tenantId)->get();
            if ($rows->isNotEmpty()) return $rows->toArray();
        }
        return $q->whereNull('tenant_id')->get()->toArray();
    }
}
