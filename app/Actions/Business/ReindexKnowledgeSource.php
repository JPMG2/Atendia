<?php

declare(strict_types=1);

namespace App\Actions\Business;

use App\Jobs\IndexKnowledgeDocument;
use App\Models\Business;

/**
 * The owner's "reindex" button. Generated sources rebuild their content
 * (the observer only re-embeds what actually changed); stored ones just
 * queue their embedding again.
 */
class ReindexKnowledgeSource
{
    public function __construct(
        private SyncProfileKnowledge $profile,
        private SyncOfferKnowledge $offer,
    ) {}

    public function handle(Business $business, string $type): void
    {
        match ($type) {
            'profile' => $this->profile->handle($business),
            'services' => $this->offer->handle($business, 'services'),
            'products' => $this->offer->handle($business, 'products'),
            default => $business->knowledgeDocuments()
                ->where('source_type', $type)
                ->pluck('id')
                ->each(fn (int $id) => IndexKnowledgeDocument::dispatch($id)),
        };
    }
}
