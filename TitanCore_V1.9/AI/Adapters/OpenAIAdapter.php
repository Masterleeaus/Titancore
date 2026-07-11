<?php
namespace Modules\TitanCore\AI\Adapters;
use Modules\TitanCore\Contracts\AI\ClientInterface;
use Illuminate\Support\Facades\Http;

class OpenAIAdapter implements ClientInterface {
  protected array $cfg;
  public function __construct(array $cfg){ $this->cfg=$cfg; }
  public function chat(array $messages, array $options=[]): array {
    $model = $options['model'] ?? ($this->cfg['chat_model'] ?? 'gpt-4o-mini');
    $resp = Http::withToken($this->cfg['api_key'])
      ->post(($this->cfg['base_url'] ?? 'https://api.openai.com/v1').'/chat/completions', [
        'model'=>$model, 'messages'=>$messages
      ]);
    return $resp->ok()? $resp->json() : ['error'=>$resp->body()];
  }
  public function embed(string $input, array $options=[]): array {
    $model = $options['model'] ?? ($this->cfg['embed_model'] ?? 'text-embedding-3-small');
    $resp = Http::withToken($this->cfg['api_key'])
      ->post(($this->cfg['base_url'] ?? 'https://api.openai.com/v1').'/embeddings', [
        'model'=>$model, 'input'=>$input
      ]);
    return $resp->ok()? $resp->json() : ['error'=>$resp->body()];
  }
}