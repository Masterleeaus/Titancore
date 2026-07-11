<?php
namespace Modules\TitanCore\Http\Controllers;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\View;
class PromptController extends Controller {
  public function index(Request $r){
    Gate::authorize('manage_ai_prompts');
    $prompts = DB::table('ai_prompts')->select('namespace','slug','version','locale','id','created_at')
      ->orderBy('namespace')->orderBy('slug')->orderByDesc('version')->limit(500)->get();
    return View::make('titancore::admin.prompts.index', compact('prompts'));
  }
  public function edit(Request $r, $namespace, $slug){
    Gate::authorize('manage_ai_prompts');
    $versions = DB::table('ai_prompts')->where('namespace',$namespace)->where('slug',$slug)
      ->orderByDesc('version')->get();
    return View::make('titancore::admin.prompts.edit', compact('namespace','slug','versions'));
  }
  public function storeVersion(Request $r, $namespace, $slug){
    Gate::authorize('publish_ai_prompts');
    $content = $r->input('content'); $locale = $r->input('locale','en');
    $latest = DB::table('ai_prompts')->where('namespace',$namespace)->where('slug',$slug)->where('locale',$locale)->max('version');
    $ver = (int)$latest + 1;
    DB::table('ai_prompts')->insert([
      'namespace'=>$namespace,'slug'=>$slug,'version'=>$ver,'locale'=>$locale,
      'content'=>$content,'metadata'=>json_encode(['source'=>'core']),
      'source'=>'core','created_at'=>now(),'updated_at'=>now()
    ]);
    return redirect()->route('titancore.prompts.edit', [$namespace,$slug])->with('status','Version '.$ver.' created');
  }
}