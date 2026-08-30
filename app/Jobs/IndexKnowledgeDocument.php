<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\KnowledgeDocument;
use App\Services\Knowledge\KnowledgeIndexer;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class IndexKnowledgeDocument implements ShouldQueue
{
    use Queueable;

    public function __construct(public int $documentId) {}

    /**
     * Indexes the document in the background; a document that is gone is a no-op.
     * On failure it marks `failed` and rethrows so the queue retries.
     */
    public function handle(KnowledgeIndexer $indexer): void
    {
        $document = KnowledgeDocument::find($this->documentId);

        if ($document === null) {
            return;
        }

        try {
            $indexer->index($document);
        } catch (Throwable $e) {
            $document->forceFill(['status' => 'failed'])->save();

            throw $e;
        }
    }
}
