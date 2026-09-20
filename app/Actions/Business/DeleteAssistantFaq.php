<?php

declare(strict_types=1);

namespace App\Actions\Business;

use App\Models\KnowledgeDocument;

/**
 * Deleting a taught answer takes its chunks with it (FK cascade), so the
 * assistant forgets it on the next retrieval — no reindex needed.
 */
class DeleteAssistantFaq
{
    public function handle(KnowledgeDocument $faq): void
    {
        $faq->delete();
    }
}
