<?php

declare(strict_types=1);

namespace App\Services\Help;

use App\Models\HelpArticle;
use App\Services\Knowledge\KnowledgeEmbedder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Cache;
use Throwable;

/**
 * Words first, meaning only if the words found nothing. The order is the whole
 * economy of this: the lexical lane is free and answers most of the time, so
 * an embedding is paid for only when someone named the problem with words we
 * never wrote — which is exactly when it is worth paying for.
 */
class HelpFinder
{
    /** Below this a sentence is still being typed and means little. */
    private const int MEANING_FROM_CHARS = 20;

    public function __construct(private readonly KnowledgeEmbedder $embedder) {}

    /**
     * @return Collection<int, HelpArticle>
     */
    public function find(?string $screen, string $term, int $limit = HelpArticle::SUGGESTIONS, bool $wide = false): Collection
    {
        $term = trim($term);

        // The help screen casts a wide net on purpose: she is looking there.
        // A hint offered beside the report box has to be precise instead.
        $byWords = $wide
            ? HelpArticle::searchAll($term, $limit)
            : HelpArticle::suggest($screen, $term, $limit);

        if ($byWords->isNotEmpty() || $term === '') {
            return $byWords;
        }

        $vector = $this->vectorFor($term);

        return $vector === null ? $byWords : HelpArticle::closestInMeaning($vector, $limit);
    }

    /**
     * ONE embedding per distinct question, cached for a day: the same unknown
     * wording tends to arrive from several people at once.
     *
     * @return list<float>|null
     */
    private function vectorFor(string $term): ?array
    {
        if (mb_strlen($term) < self::MEANING_FROM_CHARS) {
            return null;
        }

        try {
            return Cache::remember(
                'help:vector:'.hash('xxh128', mb_strtolower($term)),
                now()->addDay(),
                fn (): array => $this->embedder->embedOne($term),
            );
        } catch (Throwable $e) {
            // Help that dies with its provider is worse than help that finds
            // less: the words lane already answered, even if with nothing.
            report($e);

            return null;
        }
    }
}
