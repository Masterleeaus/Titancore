<?php
namespace Modules\TitanCore\Services;

use Illuminate\Support\Facades\DB;

class KBHealthService
{
    public function summary(?int $tenantId = null): array
    {
        if (!DB::getSchemaBuilder()->hasTable('ai_kb_documents')) return [];

        $docs = DB::table('ai_kb_documents')->count();
        $chunks = DB::table('ai_kb_chunks')->count();
        $embedded = DB::table('ai_kb_chunks')->whereNotNull('embedding')->count();

        $byCollection = [];
        if (DB::getSchemaBuilder()->hasTable('ai_kb_collection_docs')) {
            $rows = DB::table('ai_kb_collection_docs as cd')
                ->join('ai_kb_collections as c','c.id','=','cd.collection_id')
                ->selectRaw('c.key_slug as key_slug, count(distinct cd.document_id) as documents')
                ->groupBy('c.key_slug')
                ->get();
            foreach ($rows as $r) {
                $byCollection[$r->key_slug] = (int)$r->documents;
            }
        }

        return [
            'documents' => $docs,
            'chunks' => $chunks,
            'embedded' => $embedded,
            'embed_ratio' => $chunks > 0 ? round(($embedded/$chunks)*100,1) : 0,
            'collections' => $byCollection,
        ];
    }

    public function missingEmbeddings(int $limit = 500): array
    {
        if (!DB::getSchemaBuilder()->hasTable('ai_kb_chunks')) return [];
        return DB::table('ai_kb_chunks')
            ->whereNull('embedding')
            ->limit($limit)
            ->get()
            ->toArray();
    }
}
