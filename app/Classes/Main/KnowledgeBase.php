<?php

declare(strict_types=1);

namespace App\Classes\Main;

use App\Models\Business;
use App\Models\KnowledgeDocument;
use App\Models\KnowledgeMiss;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

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

            return $this->business->knowledgeDocuments()
                ->where('source_type', 'faq')
                ->latest('id')
                ->get()
                ->each(fn (KnowledgeDocument $faq) => $faq->setAttribute('times_used', $usage[$faq->id] ?? 0));
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
     * The teaching queue: what customers asked and the knowledge could not
     * answer, last 30 days, most-asked first. Folded so "¿aceptan visa?"
     * and "Aceptan VISA" count as one question.
     *
     * @var list<array{question: string, count: int, conversation_id: ?int}>
     */
    public array $misses {
        get => $this->foldedMisses(now()->subDays(30))
            ->map(fn (Collection $group): array => [
                'question' => (string) $group->first()->query,
                'count' => $group->count(),
                // The freshest asking that has a thread: context to read before teaching.
                'conversation_id' => $group->firstWhere('conversation_id', '!==', null)?->conversation_id,
            ])
            ->sortByDesc('count')
            ->take(5)
            ->values()
            ->all();
    }

    /** Answers taught by hand in the last seven days, for the weekly recap. */
    public int $weeklyTaughtCount {
        get => $this->business->knowledgeDocuments()
            ->where('source_type', 'faq')
            ->where('created_at', '>=', now()->subDays(7))
            ->count();
    }

    /**
     * The week's unanswered questions, folded: how many distinct ones and
     * the most asked, for the owner's WhatsApp recap.
     *
     * @var array{count: int, top: ?string}
     */
    public array $weeklyMissRecap {
        get {
            $groups = $this->foldedMisses(now()->subDays(7));

            return [
                'count' => $groups->count(),
                'top' => $groups->sortByDesc(fn (Collection $group): int => $group->count())->first()?->first()->query,
            ];
        }
    }

    /**
     * The misses since a date, grouped so "¿aceptan visa?" and "Aceptan
     * VISA" fold into one question — the ONE folding rule, shared by the
     * teaching queue and the weekly recap.
     *
     * @return \Illuminate\Support\Collection<string, Collection<int, KnowledgeMiss>>
     */
    private function foldedMisses(CarbonInterface $since): \Illuminate\Support\Collection
    {
        return $this->business->knowledgeMisses()
            ->where('created_at', '>=', $since)
            ->latest('id')
            ->get()
            ->groupBy(fn (KnowledgeMiss $miss): string => Str::ascii(mb_strtolower(trim($miss->query, ' ¿?.!'))));
    }
}
