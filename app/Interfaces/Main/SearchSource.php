<?php

declare(strict_types=1);

namespace App\Interfaces\Main;

use App\Dto\SearchHitDto;
use Illuminate\Support\Collection;

/**
 * WHERE the global search looks. Registered by key in
 * config('atendia.search.sources'); the palette never names a model.
 *
 * A source answers two lanes separately — words and meaning — and the service
 * fuses them, so a source that has no vectors simply returns nothing semantic.
 */
interface SearchSource
{
    /** Translation key of the group header the hits are listed under. */
    public string $group { get; }

    public string $icon { get; }

    /** Lower is sooner: groups keep their place so the eye can learn it. */
    public int $order { get; }

    /**
     * Hits whose words match, best first.
     *
     * @return Collection<int, SearchHitDto>
     */
    public function byWords(string $term, int $limit): Collection;

    /**
     * Hits whose meaning is closest, best first. Empty when the source has
     * no vectors: embeddings smear codes and phone numbers, so a source of
     * identifiers is better off without them.
     *
     * @param  list<float>  $vector
     * @return Collection<int, SearchHitDto>
     */
    public function byMeaning(array $vector, int $limit): Collection;
}
