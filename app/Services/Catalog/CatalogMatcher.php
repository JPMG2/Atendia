<?php

declare(strict_types=1);

namespace App\Services\Catalog;

use App\Models\Product;
use App\Models\Service;
use App\Services\Knowledge\KnowledgeEmbedder;
use Illuminate\Support\Collection;

/**
 * "The blue dress" → the catalog rows that mean it, by vector across services
 * AND products. One definition for every skill that looks an item up: the
 * text answer and the photos must agree on what "that one" is.
 */
class CatalogMatcher
{
    /** @return Collection<int, array{id: int, name: string, similarity: float, model: class-string<Product|Service>}> */
    public function matches(string $item, int $limit): Collection
    {
        $vector = app(KnowledgeEmbedder::class)->embedOne($item);
        $floor = (float) config('atendia.assistant.catalog_search_similarity');

        return collect([
            ...array_map(fn (array $hit): array => [...$hit, 'model' => Service::class], Service::closestMany($vector, $limit)),
            ...array_map(fn (array $hit): array => [...$hit, 'model' => Product::class], Product::closestMany($vector, $limit)),
        ])
            ->filter(fn (array $hit): bool => $hit['similarity'] >= $floor)
            ->sortByDesc('similarity')
            ->take($limit)
            ->values();
    }
}
