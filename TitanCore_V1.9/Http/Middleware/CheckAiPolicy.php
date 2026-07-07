<?php
namespace Modules\TitanCore\Http\Middleware;
use Closure;
class CheckAiPolicy {
  public function handle($request, Closure $next){
    $tenantId = $request->user()->tenant_id ?? null;
    $model = data_get($request->all(), 'options.model');
    if (!$model) return $next($request);
    $allow = config('titancore.policies.overrides.'.$tenantId, config('titancore.policies.default_allow'));
    if (!in_array($model, $allow)){
      return response()->json(['error'=>"Model $model not allowed for this tenant"], 403);
    }
    return $next($request);
  }
}