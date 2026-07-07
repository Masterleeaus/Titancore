<?php
namespace Modules\TitanCore\Services;

use Illuminate\Support\Facades\DB;

class UsageCostLogger
{
    public function __construct(protected UsageCostService $costs) {}

    public function logFromOpenAIResponse(string $feature, array $response, array $ctx = []): void
    {
        if (!DB::getSchemaBuilder()->hasTable('titan_ai_usage')) return;

        $usage = $response['usage'] ?? null;
        if (!is_array($usage)) return;

        $prompt = (int)($usage['prompt_tokens'] ?? 0);
        $completion = (int)($usage['completion_tokens'] ?? 0);
        $total = (int)($usage['total_tokens'] ?? ($prompt + $completion));

        $model = (string)($response['model'] ?? ($ctx['model'] ?? ''));
        $provider = (string)($ctx['provider'] ?? 'openai');

        $cost = $this->costs->costUsd($feature, $model, $prompt, $completion, $total);

        DB::table('titan_ai_usage')->insert([
            'tenant_id' => $ctx['tenant_id'] ?? null,
            'user_id' => $ctx['user_id'] ?? null,
            'run_id' => $ctx['run_id'] ?? null,
            'agent_slug' => $ctx['agent_slug'] ?? null,
            'feature' => $feature,
            'provider' => $provider,
            'model' => $model,
            'prompt_tokens' => $prompt,
            'completion_tokens' => $completion,
            'total_tokens' => $total,
            'cost_usd' => $cost,
            'meta' => json_encode($ctx),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
