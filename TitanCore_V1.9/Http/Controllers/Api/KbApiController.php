<?php
namespace Modules\TitanCore\Http\Controllers\Api;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\TitanCore\Services\EmbeddingService;
use Modules\TitanCore\Services\KnowledgeSearchService;

class KbApiController extends Controller {
  public function __construct(private EmbeddingService $embeddings) {}
  public function ingest(Request $r){
    $this->authorize('ingest_ai_kb');
    $tenantId = $r->user()->tenant_id ?? null;
    $collectionKey = $r->input('collection');
    $title = $r->input('title', 'Manual');
    $content = $r->input('content');
    $withEmbeddings = filter_var($r->input('embed', true), FILTER_VALIDATE_BOOL);
    if (!$collectionKey || !$content) return response()->json(['error'=>'collection and content required'], 422);
    $coll = DB::table('ai_kb_collections')->where('key_slug',$collectionKey)->first();
    $cid = $coll->id ?? DB::table('ai_kb_collections')->insertGetId([
      'tenant_id'=>$tenantId,'key_slug'=>$collectionKey,
      'title'=>Str::title(str_replace('_',' ',$collectionKey)),
      'meta'=>json_encode([]),'created_at'=>now(),'updated_at'=>now()
    ]);
    $sourceId = DB::table('ai_kb_sources')->insertGetId([
      'tenant_id'=>$tenantId,'source_type'=>'upload','display_name'=>$title,
      'meta'=>json_encode([]),'created_at'=>now(),'updated_at'=>now()
    ]);
    $docId = DB::table('ai_kb_documents')->insertGetId([
      'source_id'=>$sourceId,'external_id'=>null,'title'=>$title,'mime'=>'text/plain',
      'lang'=>'en','meta'=>json_encode([]),'created_at'=>now(),'updated_at'=>now()
    ]);
    DB::table('ai_kb_collection_docs')->insertOrIgnore(['collection_id'=>$cid,'document_id'=>$docId]);
    $parts = preg_split("/\n\n+/", $content);
    $idx = 0;
    foreach ($parts as $ch) {
      $ch = trim($ch); if ($ch==='') continue;
      $embedding = null;
      if ($withEmbeddings) {
        $e = $this->embeddings->embedText($ch);
        if (!isset($e['error'])) $embedding = json_encode($e['vector']);
      }
      DB::table('ai_kb_chunks')->insert([
        'document_id'=>$docId,'chunk_index'=>$idx++,'content'=>$ch,
        'embedding'=>$embedding,'tokens'=>strlen($ch),'meta'=>json_encode([]),
        'created_at'=>now(),'updated_at'=>now()
      ]);
    }
    return response()->json(['status'=>'ok','document_id'=>$docId,'chunks'=>$idx]);
  }
  public function search(Request $r, KnowledgeSearchService $search){
    $collectionKey = $r->query('collection');
    $q = $r->query('q','');
    $tenantId = $r->user()?->tenant_id ?? null;
    if (!$collectionKey || !$q) return response()->json(['error'=>'collection and q required'], 422);
    $results = $search->searchCollection($collectionKey, $q, $tenantId, 8);
    return response()->json(['results'=>$results]);
  }
}