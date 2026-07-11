<?php
namespace Modules\TitanCore\AI\Adapters;
use Modules\TitanCore\Contracts\AI\ClientInterface;

class OpenAIAdapter implements ClientInterface {
  protected array $cfg;
  protected const DISABLED_REASON = 'Direct OpenAI calls are disabled in TitanCore Pass 1. Use the TitanZero gateway.';
  public function __construct(array $cfg){ $this->cfg=$cfg; }
  public function chat(array $messages, array $options=[]): array {
    if (empty($this->cfg['api_key'])) {
      return ['error' => 'Missing OPENAI_API_KEY'];
    }

    return ['error' => self::DISABLED_REASON];
  }
  public function embed(string $input, array $options=[]): array {
    if (empty($this->cfg['api_key'])) {
      return ['error' => 'Missing OPENAI_API_KEY'];
    }

    return ['error' => self::DISABLED_REASON];
  }
}
