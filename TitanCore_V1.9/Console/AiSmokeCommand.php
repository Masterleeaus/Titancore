<?php
namespace Modules\TitanCore\Console;
use Illuminate\Console\Command;
use Modules\TitanCore\Contracts\AI\ClientInterface;
class AiSmokeCommand extends Command {
  protected $signature = 'ai:smoke {--model=}';
  protected $description = 'Quick health check against default AI provider';
  public function handle(ClientInterface $client){
    $model = $this->option('model');
    $resp = $client->chat([['role'=>'user','content'=>'Say "ok"']], ['model'=>$model]);
    if (isset($resp['error'])) { $this->error($resp['error']); return 1; }
    $this->info('OK'); return 0;
  }
}