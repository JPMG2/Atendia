<?php

declare(strict_types=1);

namespace App\Classes\Main;

use App\Models\Business;
use App\Models\KnowledgeDocument;
use App\Models\KnowledgeMiss;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
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
     * The hand-taught answers, newest first.
     *
     * @var Collection<int, KnowledgeDocument>
     */
    public Collection $faqs {
        get => $this->business->knowledgeDocuments()
            ->where('source_type', 'faq')
            ->latest('id')
            ->get();
    }

    /** One taught answer of THIS business, or null when it is not the tenant's. */
    public function faq(int $id): ?KnowledgeDocument
    {
        return $this->business->knowledgeDocuments()
            ->where('source_type', 'faq')
            ->find($id);
    }

    /**
     * The teaching queue: what customers asked and the knowledge could not
     * answer, last 30 days, most-asked first. Folded so "¿aceptan visa?"
     * and "Aceptan VISA" count as one question.
     *
     * @var list<array{question: string, count: int}>
     */
    public array $misses {
        get => $this->business->knowledgeMisses()
            ->where('created_at', '>=', now()->subDays(30))
            ->latest('id')
            ->get()
            ->groupBy(fn (KnowledgeMiss $miss): string => Str::ascii(mb_strtolower(trim($miss->query, ' ¿?.!'))))
            ->map(fn (Collection $group): array => [
                'question' => (string) $group->first()->query,
                'count' => $group->count(),
            ])
            ->sortByDesc('count')
            ->take(5)
            ->values()
            ->all();
    }
}
