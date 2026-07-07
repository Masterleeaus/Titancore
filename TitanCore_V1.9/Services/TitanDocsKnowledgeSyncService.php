<?php
namespace Modules\TitanCore\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Pass 5: Service version of titan:kb:sync-titandocs.
 */
class TitanDocsKnowledgeSyncService
{
    public function __construct(private EmbeddingService $embeddings) {}

    public function sync(?int $tenantId = null, bool $withEmbeddings = false, bool $includeDrafts = false, int $limit = 0): array
    {
        if (!DB::getSchemaBuilder()->hasTable('ai_templates')) {
            return ['ok'=>false,'reason'=>'ai_templates table not found (TitanDocs not migrated)'];
        }

        $q = DB::table('ai_templates');
        if (!$includeDrafts && DB::getSchemaBuilder()->hasColumn('ai_templates','status')) {
            $q->where('status', 1);
        }
        if (!$includeDrafts && DB::getSchemaBuilder()->hasColumn('ai_templates','approved_at')) {
            $q->whereNotNull('approved_at');
        }
        if ($limit > 0) $q->limit($limit);
        $templates = $q->get();

        $countDocs = 0; $countChunks = 0;

        foreach ($templates as $t) {
            $kbKey = $t->kb_collection_key ?? 'kb_general_cleaning';
            $collectionId = $this->resolveCollectionId($kbKey, $tenantId);

            $title = $t->name ?? ('Template '.$t->id);
            $externalId = 'titandocs_template:'.$t->id;

            $sourceId = $this->resolveSourceId($tenantId, 'TitanDocs');
            $docId = $this->upsertDocument($sourceId, $externalId, $title);

            DB::table('ai_kb_collection_docs')->insertOrIgnore([
                'collection_id' => $collectionId,
                'document_id' => $docId
            ]);

            $body = $this->buildTemplateBody((array)$t);
            $parts = preg_split("/\n\n+/", trim($body));
            $parts = array_values(array_filter(array_map('trim', $parts), fn($x)=>$x!==''));

            // deterministic resync
            DB::table('ai_kb_chunks')->where('document_id', $docId)->delete();

            $idx = 0;
            foreach ($parts as $ch) {
                $embedding = null;
                if ($withEmbeddings) {
                    $e = $this->embeddings->embedText($ch);
                    if (!isset($e['error']) && !empty($e['vector'])) {
                        $embedding = json_encode($e['vector']);
                    }
                }
                DB::table('ai_kb_chunks')->insert([
                    'document_id' => $docId,
                    'chunk_index' => $idx++,
                    'content' => $ch,
                    'embedding' => $embedding,
                    'tokens' => strlen($ch),
                    'meta' => json_encode(['source'=>'titandocs','template_id'=>$t->id, 'kb_key'=>$kbKey]),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            $countDocs++;
            $countChunks += $idx;
        }

        return ['ok'=>true,'documents'=>$countDocs,'chunks'=>$countChunks,'embed'=>$withEmbeddings,'tenant_id'=>$tenantId];
    }

    private function buildTemplateBody(array $t): string
    {
        $id = $t['id'] ?? null;
        $desc = $t['description'] ?? '';
        $code = $t['template_code'] ?? '';
        $slug = $t['slug'] ?? '';

        $prompts = [];
        if ($id && DB::getSchemaBuilder()->hasTable('ai_template_prompts')) {
            $rows = DB::table('ai_template_prompts')->where('template_id', (string)$id)->get();
            foreach ($rows as $r) {
                $k = $r->key ?? 'prompt';
                $v = $r->value ?? '';
                if (trim($v) !== '') $prompts[] = strtoupper($k).":\n".$v;
            }
        }

        $body = "TITLE: ".($t['name'] ?? 'Template')."\n";
        if ($slug) $body .= "SLUG: {$slug}\n";
        if ($code) $body .= "CODE: {$code}\n";
        if ($desc) $body .= "\nDESCRIPTION:\n{$desc}\n";
        if (!empty($prompts)) $body .= "\nPROMPTS:\n".implode("\n\n", $prompts)."\n";
        return $body;
    }

    private function resolveCollectionId(string $key, ?int $tenantId): int
    {
        $q = DB::table('ai_kb_collections')->where('key_slug', $key);
        $coll = $tenantId !== null
            ? (clone $q)->where('tenant_id', $tenantId)->first() ?? (clone $q)->whereNull('tenant_id')->first()
            : (clone $q)->whereNull('tenant_id')->first() ?? (clone $q)->first();

        if ($coll) return (int)$coll->id;

        return (int) DB::table('ai_kb_collections')->insertGetId([
            'tenant_id' => $tenantId,
            'key_slug' => $key,
            'title' => Str::title(str_replace('_',' ',$key)),
            'scope_type' => Str::startsWith($key, 'kb_agent_') ? 'agent' : 'general',
            'agent_slug' => Str::startsWith($key, 'kb_agent_') ? str_replace('kb_agent_', '', $key).'_agent' : null,
            'meta' => json_encode(['created_by'=>'TitanDocsKnowledgeSyncService']),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function resolveSourceId(?int $tenantId, string $display): int
    {
        $row = DB::table('ai_kb_sources')->where('tenant_id',$tenantId)->where('display_name',$display)->first();
        if ($row) return (int)$row->id;

        return (int) DB::table('ai_kb_sources')->insertGetId([
            'tenant_id' => $tenantId,
            'source_type' => 'api',
            'display_name' => $display,
            'meta' => json_encode([]),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function upsertDocument(int $sourceId, string $externalId, string $title): int
    {
        $row = DB::table('ai_kb_documents')->where('source_id',$sourceId)->where('external_id',$externalId)->first();
        if ($row) {
            DB::table('ai_kb_documents')->where('id',$row->id)->update(['title'=>$title,'updated_at'=>now()]);
            return (int)$row->id;
        }

        return (int) DB::table('ai_kb_documents')->insertGetId([
            'source_id'=>$sourceId,
            'external_id'=>$externalId,
            'title'=>$title,
            'mime'=>'text/plain',
            'lang'=>'en',
            'meta'=>json_encode([]),
            'created_at'=>now(),
            'updated_at'=>now(),
        ]);
    }
}
