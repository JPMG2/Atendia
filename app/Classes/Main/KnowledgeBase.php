<?php

declare(strict_types=1);

namespace App\Classes\Main;

use App\Enums\SuggestionStatus;
use App\Models\Business;
use App\Models\KnowledgeDocument;
use App\Models\KnowledgeSuggestion;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * The "Lo que sabe tu asistente" piece: every source the assistant answers
 * from, made visible and teachable. The black box is the fear that stalls
 * the sale (audit, 2026-09-20); this piece opens it.
 */
class KnowledgeBase
{
    /** The automatic feeds, in the order the owner fills them. */
    private const array AUTO_SOURCES = ['profile', 'services', 'products', 'import'];

    public function __construct(private Business $business) {}

    /**
     * One row per automatic feed: whether it exists, how many fragments the
     * assistant holds, and when it last learned.
     *
     * @var list<array{type: string, present: bool, chunks: int, indexed_at: ?Carbon}>
     */
    public array $sources {
        get => collect(self::AUTO_SOURCES)->map(function (string $type): array {
            $documents = $this->business->knowledgeDocuments()
                ->where('source_type', $type)
                ->withCount('chunks')
                ->get();

            return [
                'type' => $type,
                'present' => $documents->isNotEmpty(),
                'chunks' => (int) $documents->sum('chunks_count'),
                'indexed_at' => $documents->max('indexed_at'),
            ];
        })->all();
    }

    /**
     * The hand-taught answers, newest first, each carrying `times_used`:
     * how often the assistant's replies cited it, read from the provenance
     * trail. Proof that teaching pays.
     *
     * @var Collection<int, KnowledgeDocument>
     */
    public Collection $faqs {
        get {
            $usage = $this->usageByDocument();
            $recovery = $this->recoveryByDocument();

            return $this->business->knowledgeDocuments()
                ->where('source_type', 'faq')
                ->latest('id')
                ->get()
                ->each(fn (KnowledgeDocument $faq) => $faq->setAttribute('times_used', $usage[$faq->id] ?? 0)
                    ->setAttribute('recovery', $recovery[$faq->id] ?? null));
        }
    }

    /**
     * How many assistant replies cited each document, unrolled from the
     * jsonb trail in one query.
     *
     * @return array<int, int> document id => citations
     */
    private function usageByDocument(): array
    {
        $rows = DB::select(<<<'SQL'
            select (source->>'id')::bigint as document_id, count(*) as uses
            from conversation_messages, jsonb_array_elements(knowledge_sources) as source
            where business_id = ? and knowledge_sources is not null
            group by 1
            SQL, [$this->business->id]);

        return collect($rows)->mapWithKeys(fn (object $row): array => [(int) $row->document_id => (int) $row->uses])->all();
    }

    /**
     * Per taught answer, the customers it was sent to after going without,
     * how many days they had waited on average, and how many wrote back:
     * the proof that teaching wins customers back.
     *
     * @return array<int, array{sent: int, days: int, returned: int}> document id => recovery
     */
    private function recoveryByDocument(): array
    {
        $rows = DB::select(<<<'SQL'
            select s.knowledge_document_id as document_id,
                   count(distinct q.conversation_id) as sent,
                   avg(extract(epoch from q.customer_notified_at - q.created_at)) / 86400 as days,
                   count(distinct q.conversation_id) filter (where exists (
                       select 1 from conversation_messages m
                       where m.conversation_id = q.conversation_id and m.direction = 'in'
                         and m.created_at > q.customer_notified_at
                   )) as returned
            from conversation_questions q
            join knowledge_suggestions s on s.id = q.knowledge_suggestion_id
            where q.business_id = ? and q.customer_notified_at is not null and s.knowledge_document_id is not null
            group by 1
            SQL, [$this->business->id]);

        return collect($rows)->mapWithKeys(fn (object $row): array => [(int) $row->document_id => [
            'sent' => (int) $row->sent,
            'days' => (int) round((float) $row->days),
            'returned' => (int) $row->returned,
        ]])->all();
    }

    /** One taught answer of THIS business, or null when it is not the tenant's. */
    public function faq(int $id): ?KnowledgeDocument
    {
        return $this->business->knowledgeDocuments()
            ->where('source_type', 'faq')
            ->find($id);
    }

    /** One knowledge document of ANY source, or null when it is not the tenant's. */
    public function document(int $id): ?KnowledgeDocument
    {
        return $this->business->knowledgeDocuments()->find($id);
    }

    /**
     * "Tu asistente no supo esto", grouped by topic, the most asked topic
     * first; inside, most asked question first.
     *
     * @var \Illuminate\Support\Collection<string, Collection<int, KnowledgeSuggestion>>
     */
    public \Illuminate\Support\Collection $suggestions {
        get => $this->business->knowledgeSuggestions()
            ->queue()
            ->get()
            ->groupBy(fn (KnowledgeSuggestion $suggestion): string => $suggestion->intent->name ?? __('client.assistant.suggestions_other'))
            ->sortByDesc(fn (Collection $topic): int => (int) $topic->sum('asked_count'));
    }

    /** One pending suggestion of THIS business, or null when it is not the tenant's. */
    public function suggestion(int $id): ?KnowledgeSuggestion
    {
        return $this->business->knowledgeSuggestions()->queue()->find($id);
    }

    /** One suggestion of THIS business already taught, with its answer, or null. */
    public function taughtSuggestion(int $id): ?KnowledgeSuggestion
    {
        return $this->business->knowledgeSuggestions()
            ->where('status', SuggestionStatus::Taught)
            ->with('document')
            ->find($id);
    }

    /** Answers taught by hand in the last seven days, for the weekly recap. */
    public int $weeklyTaughtCount {
        get => $this->business->knowledgeDocuments()
            ->where('source_type', 'faq')
            ->where('created_at', '>=', now()->subDays(7))
            ->count();
    }

    /**
     * The week's queue for the owner's WhatsApp recap: how many pending
     * questions were asked in the last seven days and the most asked one.
     *
     * @var array{count: int, top: ?string}
     */
    public array $weeklySuggestionRecap {
        get {
            $week = $this->business->knowledgeSuggestions()
                ->queue()
                ->whereHas('questions', fn (Builder $asked): Builder => $asked->where('created_at', '>=', now()->subDays(7)))
                ->get();

            return ['count' => $week->count(), 'top' => $week->first()?->question];
        }
    }
}
