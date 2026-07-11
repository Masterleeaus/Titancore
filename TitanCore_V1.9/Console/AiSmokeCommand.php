<?php
namespace Modules\TitanCore\Console;
use Illuminate\Console\Command;
use Modules\TitanCore\Services\TitanCoreModelGateway;
class AiSmokeCommand extends Command {
  protected $signature = 'ai:smoke {--model=}';
  protected $description = 'Quick health check against default AI provider';
  public function handle(TitanCoreModelGateway $gateway){
    $model = $this->option('model');
    $context = [
      'tenant_id' => null,
      'module' => 'TitanCore',
      'operation' => 'ai_smoke',
    ];
    $resp = $gateway->chat(
      [['role'=>'user','content'=>'Say "ok"']],
      $context,
      ['model'=>$model]
    );
    if (isset($resp['error'])) { $this->error($resp['error']); return 1; }
    $this->info('OK'); return 0;
  }
}