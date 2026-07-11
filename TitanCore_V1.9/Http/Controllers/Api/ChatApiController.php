<?php
namespace Modules\TitanCore\Http\Controllers\Api;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Modules\TitanCore\Contracts\AI\ClientInterface;
use Modules\TitanCore\Support\Idempotency;
use Modules\TitanCore\Events\AiRequestCompleted;

class ChatApiController extends Controller {
  public function chat(Request $r, ClientInterface $client){
    $messages = $r->input('messages', []);
    $options  = $r->input('options', []);
    if (!is_array($messages) || empty($messages)) return response()->json(['error'=>'messages[] required'], 422);
    $tenantId = $r->user()->tenant_id ?? null;
    $corr = Idempotency::key($r->header('X-Idempotency-Key'), ['messages'=>$messages,'options'=>$options]);
    $prev = DB::table('ai_usage_ledger')->where('correlation_id',$corr)->first();
    if ($prev && isset($prev->meta)) {
      $meta = json_decode($prev->meta, true);
      if (!empty($meta['response'])) return response()->json($meta['response']);
    }
    $started = microtime(true);
    $status = 'ok'; $tokensIn = 0; $tokensOut = 0; $cost = 0.0; $resp = null;
    try {
      $resp = $client->chat($messages, $options);
      if (isset($resp['error'])) { $status = 'error'; }
      $tokensIn  = $resp['usage']['prompt_tokens']     ?? 0;
      $tokensOut = $resp['usage']['completion_tokens'] ?? 0;
      $cost      = $resp['usage']['total_cost']        ?? 0;
    } catch (\Throwable $e) {
      $status = 'error'; $resp = ['error'=>$e->getMessage()];
    } finally {
      $meta = [
        'provider' => 'openai',
        'model'    => $options['model'] ?? null,
        'response' => $resp,
        'latency_ms' => (int) ((microtime(true) - $started) * 1000),
      ];
      DB::table('ai_usage_ledger')->updateOrInsert(
        ['correlation_id'=>$corr],
        [
          'tenant_id'=>$tenantId,
          'module'=>$r->input('module'),
          'operation'=>$r->input('operation'),
          'tokens_in'=>$tokensIn,
          'tokens_out'=>$tokensOut,
          'cost'=>$cost,
          'status'=>$status,
          'meta'=>json_encode($meta),
          'updated_at'=>now(),
          'created_at'=>now(),
        ]
      );
      event(new AiRequestCompleted($corr, $status, $meta['latency_ms'], $tokensIn, $tokensOut, (float)$cost));
    }
    return response()->json($resp);
  }
}