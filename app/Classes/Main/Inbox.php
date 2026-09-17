<?php

declare(strict_types=1);

namespace App\Classes\Main;

use App\Models\Business;
use App\Models\Conversation;
use App\Services\Knowledge\KnowledgeEmbedder;
use Illuminate\Database\Eloquent\Collection;

/**
 * The inbox piece: every thread the assistant holds with this tenant's
 * customers. Read-only by design — the assistant is the one who writes;
 * the owner's replies arrive with the human-handoff phase.
 */
class Inbox
{
    public function __construct(private Business $business) {}

    /**
     * Every thread, freshest exchange first, each carrying its preview so
     * the list costs two queries, never one per row.
     *
     * @var Collection<int, Conversation>
     */
    public Collection $threads {
        get => $this->business->conversations()
            ->with('latestMessage')
            ->orderByDesc('last_message_at')
            ->get();
    }

    /** One thread with its full history, or null when it is not this tenant's. */
    public function thread(int $id): ?Conversation
    {
        return $this->business->conversations()
            ->with('messages')
            ->find($id);
    }

    /** Today's pulse for the inbox header. */
    public int $todayCount {
        get => $this->business->conversations()
            ->whereDate('last_message_at', today())
            ->count();
    }

    /**
     * Meaning-based fallback for the inbox search: the customer questions
     * closest to the query, in the same vector space the RAG uses, folded
     * back into their threads. The similarity floor mirrors retrieval's —
     * beyond it, results read as random and erode trust in the search.
     *
     * @return Collection<int, Conversation>
     */
    public function searchThreads(string $query): Collection
    {
        $vector = app(KnowledgeEmbedder::class)->embedOne($query);
        $maxDistance = 1.0 - (float) config('rag.retrieval.min_similarity');

        $conversationIds = $this->business->conversations()
            ->join('conversation_messages', 'conversation_messages.conversation_id', '=', 'conversations.id')
            ->whereNotNull('conversation_messages.embedding')
            ->selectVectorDistance('conversation_messages.embedding', $vector, as: 'distance')
            ->addSelect('conversations.id')
            ->orderByVectorDistance('conversation_messages.embedding', $vector)
            ->limit(12)
            ->get()
            ->filter(fn ($row): bool => (float) $row->getAttribute('distance') <= $maxDistance)
            ->pluck('id')
            ->unique()
            ->values();

        return $this->business->conversations()
            ->with('latestMessage')
            ->whereIn('id', $conversationIds)
            ->get()
            ->sortBy(fn (Conversation $thread): int => $conversationIds->search($thread->id))
            ->values();
    }
}
