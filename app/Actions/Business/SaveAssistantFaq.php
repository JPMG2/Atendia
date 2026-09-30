<?php

declare(strict_types=1);

namespace App\Actions\Business;

use App\Messaging\Channels\Panel;
use App\Messaging\Panel\TaughtByTeammate;
use App\Models\Business;
use App\Models\KnowledgeDocument;
use Illuminate\Support\Facades\Auth;

/**
 * A hand-taught answer becomes a knowledge document: the observer hashes it
 * and queues the indexing, so the assistant knows it within the minute.
 */
class SaveAssistantFaq
{
    /**
     * Provenance only lands at birth: editing a taught answer later must not
     * rewrite which chat it was learned from. A suggestion it answers leaves
     * the queue.
     *
     * @param  array{question: string, answer: string}  $data
     */
    public function handle(Business $business, array $data, ?int $id = null, ?int $conversationId = null, ?int $suggestionId = null): KnowledgeDocument
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

        if ($suggestionId !== null) {
            $business->knowledgeSuggestions()->find($suggestionId)?->markTaught($document);
        }

        $this->tellTheOwner($business, $document);

        return $document;
    }

    /**
     * When the teacher is not the owner, the bell says so. Told, not asked: the
     * answer is already live, and an approval queue on the busiest person is
     * what makes a team stop proposing answers at all.
     */
    private function tellTheOwner(Business $business, KnowledgeDocument $document): void
    {
        $teacher = Auth::user();

        if ($teacher === null || $teacher->can('manage-business')) {
            return;
        }

        new Panel($document, [], TaughtByTeammate::class, [$teacher->name])->send();
    }
}
