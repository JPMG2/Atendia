<?php

declare(strict_types=1);

namespace App\Observers;

use App\Jobs\IndexKnowledgeDocument;
use App\Models\KnowledgeDocument;

class KnowledgeDocumentObserver
{
    /**
     * Recomputes the content hash and marks the document pending when the text
     * changes. Only content triggers re-indexing — editing the title does not.
     */
    public function saving(KnowledgeDocument $document): void
    {
        if ($document->isDirty('content')) {
            $document->content_hash = hash('sha256', (string) $document->content);
            $document->status = 'pending';
        }
    }

    /**
     * A new document always has content to index, so it queues once.
     */
    public function created(KnowledgeDocument $document): void
    {
        if ($document->content_hash !== null) {
            IndexKnowledgeDocument::dispatch($document->id);
        }
    }

    /**
     * Re-indexes only when the hash moved. Editing the title does not, and when
     * the job writes `status`/`indexed_at` the hash stays put — so no loops.
     */
    public function updated(KnowledgeDocument $document): void
    {
        if ($document->wasChanged('content_hash')) {
            IndexKnowledgeDocument::dispatch($document->id);
        }
    }
}
