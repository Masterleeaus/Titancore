<?php
namespace Modules\TitanCore\Services;

class UsageCostService
{
    public function costUsd(string $feature, string $model, int $promptTokens, int $completionTokens, int $totalTokens): ?float
    {
        $pricing = config('ai.pricing', []);

        $row = $pricing[$model] ?? null;
        if (!$row || !is_array($row)) return null;

        if ($feature === 'embed') {
            $rate = (float)($row['embedding_per_1k'] ?? 0);
            return round(($totalTokens / 1000.0) * $rate, 6);
        }

        $p = (float)($row['prompt_per_1k'] ?? 0);
        $c = (float)($row['completion_per_1k'] ?? 0);
        return round(($promptTokens/1000.0)*$p + ($completionTokens/1000.0)*$c, 6);
    }
}
