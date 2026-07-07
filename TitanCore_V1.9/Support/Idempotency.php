<?php
namespace Modules\TitanCore\Support;
class Idempotency {
  public static function key(?string $headerKey, array $payload): string {
    if ($headerKey && is_string($headerKey)) return $headerKey;
    return 'hash:'.hash('sha256', json_encode($payload, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES));
  }
}