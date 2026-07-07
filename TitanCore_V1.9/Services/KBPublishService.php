<?php
namespace Modules\TitanCore\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class KBPublishService
{
    /**
     * Publish an immutable snapshot for a KB collection and set it active.
     */
    public function publishCollection(string $collectionKey, ?int $tenantId = null, ?string $label = null, ?int $userId = null): array
    {
        if (!DB::getSchemaBuilder()->hasTable('ai_kb_collections')) {
            return ['ok'=>false,'reason'=>'ai_kb_collections table not found'];
        }

        $collQ = DB::table('ai_kb_collections')->where('key_slug', $collectionKey);
        $coll = $tenantId !== null
            ? (clone $collQ)->where('tenant_id', $tenantId)->first() ?? (clone $collQ)->whereNull('tenant_id')->first()
            : (clone $collQ)->whereNull('tenant_id')->first() ?? (clone $collQ)->first();

        if (!$coll) return ['ok'=>false,'reason'=>'collection not found'];

        // Gather docs for collection
        $docIds = DB::table('ai_kb_collection_docs')->where('collection_id', $coll->id)->pluck('document_id')->toArray();
        if (empty($docIds)) return ['ok'=>false,'reason'=>'no documents in collection'];

        $label = $label ?: ('Publish '.now()->format('Y-m-d H:i'));

        return DB::transaction(function () use ($coll, $docIds, $tenantId, $label, $userId) {
            $versionId = (int) DB::table('titan_ai_kb_versions')->insertGetId([
                'tenant_id' => $tenantId,
                'collection_id' => $coll->id,
                'label' => $label,
                'created_by' => $userId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // Copy chunks from live table into snapshots
            $rows = DB::table('ai_kb_chunks')->whereIn('document_id', $docIds)->orderBy('id')->get();
            $count = 0;

            foreach ($rows as $r) {
                DB::table('titan_ai_kb_chunk_snapshots')->insert([
                    'version_id' => $versionId,
                    'document_id' => (int)$r->document_id,
                    'chunk_index' => (int)($r->chunk_index ?? 0),
                    'content' => (string)$r->content,
                    'embedding' => $r->embedding,
                    'meta' => $r->meta ?? null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                $count++;
            }

            // Activate version on collection row
            if (DB::getSchemaBuilder()->hasColumn('ai_kb_collections','active_version_id')) {
                DB::table('ai_kb_collections')->where('id',$coll->id)->update([
                    'active_version_id' => $versionId,
                    'updated_at' => now(),
                ]);
            }

            return ['ok'=>true,'version_id'=>$versionId,'collection_id'=>(int)$coll->id,'documents'=>count($docIds),'chunks'=>$count,'label'=>$label];
        });
    }
}
