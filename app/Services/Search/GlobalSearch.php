<?php

declare(strict_types=1);

namespace App\Services\Search;

use App\Dto\SearchHitDto;
use App\Interfaces\Main\SearchSource;
use App\Services\Knowledge\KnowledgeEmbedder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Throwable;

/**
 * Two lanes and one list. Words find identifiers a vector would smear (a code,
 * a phone); meaning finds the item the owner described instead of named. The
 * two are fused by POSITION — a lexical score and a cosine distance are not
 * comparable numbers, so adding them would be inventing arithmetic.
 */
class GlobalSearch
{
    /** The constant from the RRF paper. Tune the candidate pool, not this. */
    private const int RRF_K = 60;

    /** Below this, a query is a prefix being typed and meaning says nothing. */
    private const int MEANING_FROM_CHARS = 4;

    public function __construct(private readonly KnowledgeEmbedder $embedder) {}

    /**
     * @return Collection<string, Collection<int, SearchHitDto>> group key => hits
     */
    public function find(string $term, int $perGroup = 5): Collection
    {
        $term = trim($term);

        if (mb_strlen($term) < 2) {
            return collect();
        }

        $vector = $this->vectorFor($term);
        $pool = $perGroup * 3;

        return $this->sources()
            ->mapWithKeys(function (SearchSource $source) use ($term, $vector, $pool, $perGroup): array {
                $words = $this->ranked($source->byWords($term, $pool));
                $meaning = $vector === null ? collect() : $this->ranked($source->byMeaning($vector, $pool));

                $fused = $this->fuse($words, $meaning)->take($perGroup);

                return $fused->isEmpty() ? [] : [$source->group => $fused];
            });
    }

    /**
     * Both lists keep their own order; a row in both climbs. Sum of 1/(k+rank).
     *
     * @param  Collection<int, SearchHitDto>  $words
     * @param  Collection<int, SearchHitDto>  $meaning
     * @return Collection<int, SearchHitDto>
     */
    private function fuse(Collection $words, Collection $meaning): Collection
    {
        $scores = [];
        $hits = [];

        foreach ([$words, $meaning] as $lane) {
            foreach ($lane as $hit) {
                $scores[$hit->key] = ($scores[$hit->key] ?? 0.0) + 1 / (self::RRF_K + $hit->rank);

                // The words lane wins the row it shares: its subtitle carries
                // the code or the phone the owner is looking at.
                $hits[$hit->key] ??= $hit;
            }
        }

        arsort($scores);

        return collect(array_keys($scores))->map(fn (string $key): SearchHitDto => $hits[$key]);
    }

    /**
     * @param  Collection<int, SearchHitDto>  $hits
     * @return Collection<int, SearchHitDto>
     */
    private function ranked(Collection $hits): Collection
    {
        return $hits->values()->map(fn (SearchHitDto $hit, int $index): SearchHitDto => $hit->atRank($index + 1));
    }

    /**
     * ONE embedding per distinct query, cached: the meaning lane costs the
     * same whether it serves one source or four, and a repeated search is free.
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
                'search:vector:'.hash('xxh128', mb_strtolower($term)),
                now()->addDay(),
                fn (): array => $this->embedder->embedOne($term),
            );
        } catch (Throwable $e) {
            // The words lane still answers: a palette that dies because the
            // embeddings provider is down is worse than one that finds less.
            report($e);

            return null;
        }
    }

    /**
     * @return Collection<int, SearchSource>
     */
    private function sources(): Collection
    {
        return collect(config('atendia.search.sources', []))
            ->map(fn (string $class): SearchSource => app($class))
            ->sortBy(fn (SearchSource $source): int => $source->order)
            ->values();
    }
}
