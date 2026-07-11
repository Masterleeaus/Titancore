<?php

namespace Modules\TitanCore\AI;

use Illuminate\Support\Facades\DB;

/**
 * Writes tool execution audit entries to the `titan_tool_audit_log` table.
 *
 * This class implements `__invoke` so it can be passed directly as the
 * `$auditWriter` callable to {@see \Modules\TitanCore\AI\ToolExecutor}.
 *
 * Expected $entry shape:
 *   [
 *     'tool'         => string,
 *     'user_id'      => int|null,
 *     'company_id'   => int|null,
 *     'input_hash'   => string|null,   // SHA-256 of serialised input params
 *     'status'       => string,        // success|failed|timed_out|blocked|dry_run
 *     'duration_ms'  => int|null,
 *     'error'        => string|null,
 *   ]
 */
class ToolAuditWriter
{
    public function __invoke(array $entry): void
    {
        DB::table('titan_tool_audit_log')->insert([
            'tool'          => $entry['tool'],
            'user_id'       => $entry['user_id'] ?? null,
            'company_id'    => $entry['company_id'] ?? null,
            'input_hash'    => $entry['input_hash'] ?? null,
            'status'        => $entry['status'],
            'duration_ms'   => $entry['duration_ms'] ?? null,
            'error_message' => $entry['error'] ?? null,
            'created_at'    => now(),
            'updated_at'    => now(),
        ]);
    }
}
