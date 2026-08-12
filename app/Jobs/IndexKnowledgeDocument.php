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
     * Indexa el documento en segundo plano. Si desapareció (borrado), no hace nada.
     * Ante un fallo (p. ej. la API de embeddings), marca `failed` y relanza para
     * que la cola reintente.
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
