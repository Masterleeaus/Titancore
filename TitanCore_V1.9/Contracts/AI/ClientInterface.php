<?php
namespace Modules\TitanCore\Contracts\AI;
interface ClientInterface {
  public function chat(array $messages, array $options = []): array;
  public function embed(string $input, array $options = []): array;
}