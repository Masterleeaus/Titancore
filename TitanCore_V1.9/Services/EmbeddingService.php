<?php
namespace Modules\TitanCore\Services;
use Modules\TitanCore\Contracts\AI\ClientInterface;
use Modules\TitanCore\Services\UsageCostLogger;
class EmbeddingService {
  public function __construct(private ClientInterface $client, private UsageCostLogger $usage) {}
  public function embedText(string $text, array $opts = []): array {
    $res = $this->client->embed($text, $opts);
    try {
      $this->usage->logFromOpenAIResponse('embed', $res, [
        'tenant_id' => optional(auth()->user())->tenant_id,
        'user_id' => optional(auth()->user())->id,
        'provider' => 'openai',
        'model' => $opts['model'] ?? ($res['model'] ?? ''),
      ]);
    } catch (\Throwable $e) { /* ignore */ }

    if (isset($res['error'])) return ['error'=>$res['error']];
    $vec = $res['data'][0]['embedding'] ?? null;
    return ['vector'=>$vec, 'raw'=>$res];
  }
}