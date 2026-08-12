<?php

declare(strict_types=1);

namespace App\Observers;

use App\Jobs\IndexKnowledgeDocument;
use App\Models\KnowledgeDocument;

class KnowledgeDocumentObserver
{
    /**
     * Recalcula el hash del contenido y marca el documento como pendiente cuando
     * el texto cambia. Solo el contenido dispara re-indexado: editar el título no.
     */
    public function saving(KnowledgeDocument $document): void
    {
        if ($document->isDirty('content')) {
            $document->content_hash = hash('sha256', (string) $document->content);
            $document->status = 'pending';
        }
    }

    /**
     * Al crear, siempre hay contenido para indexar → encola una vez.
     */
    public function created(KnowledgeDocument $document): void
    {
        if ($document->content_hash !== null) {
            IndexKnowledgeDocument::dispatch($document->id);
        }
    }

    /**
     * Al actualizar, re-indexa SOLO si el contenido cambió (el hash cambió). Editar
     * el título no reindexar; y cuando el job escribe `status`/`indexed_at`, el hash
     * no cambia, así que no se re-dispara (nada de bucles).
     */
    public function updated(KnowledgeDocument $document): void
    {
        if ($document->wasChanged('content_hash')) {
            IndexKnowledgeDocument::dispatch($document->id);
        }
    }
}
