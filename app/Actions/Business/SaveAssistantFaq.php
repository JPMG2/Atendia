<?php

declare(strict_types=1);

namespace App\Actions\Business;

use App\Models\Business;
use App\Models\KnowledgeDocument;

/**
 * A hand-taught answer becomes a knowledge document: the observer hashes it
 * and queues the indexing, so the assistant knows it within the minute.
 */
class SaveAssistantFaq
{
    /**
     * Provenance only lands at birth: editing a taught answer later must not
     * rewrite which chat it was learned from.
     *
     * @param  array{question: string, answer: string}  $data
     */
    public function handle(Business $business, array $data, ?int $id = null, ?int $conversationId = null): KnowledgeDocument
    {
        $document = $id === null
            ? new KnowledgeDocument(['business_id' => $business->id, 'source_type' => 'faq', 'conversation_id' => $conversationId])
            : $business->knowledgeDocuments()->where('source_type', 'faq')->findOrFail($id);

        // The question travels INSIDE the content on purpose: retrieval
        // embeds the content, and customers ask with the question's words.
        $document->fill([
            'title' => $data['question'],
            'content' => 'Pregunta: '.$data['question']."\nRespuesta: ".$data['answer'],
        ])->save();

        return $document;
    }
}
