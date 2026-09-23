<?php

declare(strict_types=1);

namespace App\Classes\Main;

use App\Models\Business;
use App\Models\Conversation;
use App\Models\ConversationMessage;
use App\Services\Knowledge\KnowledgeEmbedder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Str;

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

    /**
     * One thread carrying only its latest exchanges, or null when it is not
     * this tenant's. Windowed on purpose: a 200-message thread must not ride
     * every render; `messages_count` tells the screen how far back it can walk.
     */
    public function thread(int $id, int $latest = 30): ?Conversation
    {
        $thread = $this->business->conversations()
            ->withCount(['messages', 'taughtFaqs'])
            // Eager on purpose: the badge popover lists them, and a Blade
            // must never trigger the lazy query itself.
            ->with(['taughtFaqs' => fn ($query) => $query->select('id', 'conversation_id', 'title')->latest('id')])
            ->find($id);

        if ($thread === null) {
            return null;
        }

        $thread->setRelation('messages', $thread->messages()
            ->latest('id')
            ->limit($latest)
            ->get()
            ->reverse()
            ->values());

        return $thread;
    }

    /** Today's pulse for the inbox header. */
    public int $todayCount {
        get => $this->business->conversations()
            ->whereDate('last_message_at', today())
            ->count();
    }

    /**
     * One customer question the assistant could NOT answer, or null: the
     * "teach the assistant this" door only opens where the AI triage saw
     * it fall short — never on a "sí", nor on what it already answered.
     */
    public function customerMessage(int $threadId, int $messageId): ?ConversationMessage
    {
        return $this->business->conversations()->find($threadId)
            ?->messages()
            ->whereKey($messageId)
            ->teachable()
            ->first();
    }

    /**
     * Every message of ONE thread whose text matches, accent-free, across
     * the whole history: finding an old quote is the point of searching a
     * thread, so the render window does not apply here.
     *
     * @return Collection<int, ConversationMessage>
     */
    public function threadMatches(int $id, string $needle): Collection
    {
        $thread = $this->business->conversations()->find($id);

        if ($thread === null) {
            return new Collection;
        }

        $folded = Str::ascii(mb_strtolower($needle));

        return $thread->messages()
            ->oldest('id')
            ->get()
            ->filter(fn (ConversationMessage $message): bool => str_contains(Str::ascii(mb_strtolower($message->body)), $folded))
            ->values();
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
