<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\KnowledgeDocument;
use App\Services\Knowledge\KnowledgeIndexer;
use App\Services\Tenant;
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
        $owner = KnowledgeDocument::ownerOf($this->documentId);

        if ($owner === null) {
            return;
        }

        // The worker has no session: the job adopts the document's business BEFORE
        // reading it, so the Eloquent scope and the RLS fence apply while it runs.
        app(Tenant::class)->for($owner, function () use ($indexer): void {
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
        });
    }
}
