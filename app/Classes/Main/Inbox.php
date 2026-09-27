<?php

declare(strict_types=1);

namespace App\Classes\Main;

use App\Models\Business;
use App\Models\Conversation;
use App\Models\ConversationMessage;
use App\Models\KnowledgeSuggestion;
use App\Models\User;
use App\Services\Knowledge\KnowledgeEmbedder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * The inbox piece: every thread the assistant holds with this tenant's
 * customers. Read-only by design — the assistant is the one who writes;
 * the owner's replies arrive with the human-handoff phase.
 */
class Inbox
{
    public function __construct(private Business $business, private ?User $viewer = null) {}

    /**
     * Every thread query starts here: an agent only ever reaches the threads
     * of their departments or the ones they took, whatever id they send.
     *
     * @return HasMany<Conversation, Business>
     */
    private function conversations(): HasMany
    {
        return $this->business->conversations()->when($this->viewer !== null, fn ($threads) => $threads->visibleTo($this->viewer));
    }

    /**
     * Every thread, freshest exchange first, each carrying its preview so
     * the list costs two queries, never one per row.
     *
     * @var Collection<int, Conversation>
     */
    public Collection $threads {
        get => $this->conversations()
            ->select('conversations.*')
            // Photos, documents and places per thread, in the same query: the list's paperclip count.
            ->selectSub(
                ConversationMessage::query()
                    ->selectRaw('coalesce(sum(jsonb_array_length(media)), 0)')
                    ->whereColumn('conversation_id', 'conversations.id')
                    ->whereNotNull('media'),
                'attachments_count',
            )
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
        $thread = $this->conversations()
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
        get => $this->conversations()
            // The owner's today, not UTC's: from 20:00 in Caracas it read tomorrow.
            ->whereBetween('last_message_at', [
                now($this->business->localTimezone())->startOfDay()->utc(),
                now($this->business->localTimezone())->endOfDay()->utc(),
            ])
            ->count();
    }

    /**
     * The messages of one thread whose question is still in the teaching
     * queue: the "teach the assistant this" door opens only there — never
     * on a "sí", nor on what the assistant already answered.
     *
     * @return array<int, int> message id => suggestion id
     */
    public function teachableMessages(int $threadId): array
    {
        return $this->conversations()->whereKey($threadId)->exists()
            ? KnowledgeSuggestion::pendingByMessage($threadId)
            : [];
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
        $thread = $this->conversations()->find($id);

        if ($thread === null) {
            return new Collection;
        }

        // The PDFs a customer sent count as part of the thread: "the quote in the
        // budget they sent" is exactly the old quote a search should find.
        return $thread->messages()
            ->oldest('id')
            ->get()
            ->filter(fn (ConversationMessage $message): bool => $message->matches($needle))
            ->values();
    }

    /**
     * Meaning-based fallback for the inbox search: the analyzed questions
     * closest to the query (rewritten, so "¿y los sábados?" still matches),
     * folded back into their threads. The similarity floor mirrors
     * retrieval's — beyond it, results read as random.
     *
     * @return Collection<int, Conversation>
     */
    public function searchThreads(string $query): Collection
    {
        $vector = app(KnowledgeEmbedder::class)->embedOne($query);
        $maxDistance = 1.0 - (float) config('rag.retrieval.min_similarity');

        $conversationIds = $this->conversations()
            ->join('conversation_questions', 'conversation_questions.conversation_id', '=', 'conversations.id')
            ->whereNotNull('conversation_questions.embedding')
            ->selectVectorDistance('conversation_questions.embedding', $vector, as: 'distance')
            ->addSelect('conversations.id')
            ->orderByVectorDistance('conversation_questions.embedding', $vector)
            ->limit(12)
            ->get()
            ->filter(fn ($row): bool => (float) $row->getAttribute('distance') <= $maxDistance)
            ->pluck('id')
            ->unique()
            ->values();

        return $this->conversations()
            ->with('latestMessage')
            ->whereIn('id', $conversationIds)
            ->get()
            ->sortBy(fn (Conversation $thread): int => $conversationIds->search($thread->id))
            ->values();
    }
}
