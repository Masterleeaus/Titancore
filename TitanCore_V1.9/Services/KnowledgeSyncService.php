<?php

namespace Modules\TitanCore\Services;

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Modules\TitanCore\Entities\TitanCoreSetting;

class KnowledgeSyncService
{
    /**
     * Sync the Worksuite core Knowledge Base into Titan Core's KB engine.
     */
    public function syncWorksuiteKnowledgeBase(): void
    {
        $settings   = TitanCoreSetting::getSetting();
        $collection = $settings->kb_collection_slug ?: 'worksuite_core_kb';

        $sourceConfig = Config::get('titancore.kb_sources.worksuite_core');
        if (! $sourceConfig || empty($sourceConfig['model'])) {
            return;
        }

        $modelClass = $sourceConfig['model'];
        $titleCol   = $sourceConfig['title_column'] ?? 'title';
        $bodyCol    = $sourceConfig['body_column'] ?? 'description';
        $companyCol = $sourceConfig['company_column'] ?? null;
        $updatedCol = $sourceConfig['updated_at_column'] ?? 'updated_at';

        /** @var \Illuminate\Database\Eloquent\Builder $query */
        $query = (new $modelClass())->newQuery();
        $articles = $query->get();

        /** @var \Modules\TitanCore\Services\EmbeddingService $embedding */
        $embedding = app(EmbeddingService::class);

        foreach ($articles as $article) {
            $companyId = $companyCol ? $article->{$companyCol} : null;

            // Upsert into ai_kb_documents
            DB::table('ai_kb_documents')->updateOrInsert(
                [
                    'external_id' => 'kb:' . $article->getKey(),
                    'source'      => 'worksuite_kb',
                ],
                [
                    'title'       => $article->{$titleCol},
                    'content'     => $article->{$bodyCol},
                    'company_id'  => $companyId,
                    'collection'  => $collection,
                    'updated_at'  => $article->{$updatedCol},
                    'created_at'  => now(),
                ]
            );

            // Re-chunk & embed using Titan Core's KB engine
            $embedding->ingestDocumentFromRaw(
                'kb:' . $article->getKey(),
                $article->{$titleCol},
                $article->{$bodyCol},
                $collection,
                $companyId
            );
        }
    }
}
