<?php

declare(strict_types=1);

namespace App\Services\Topics;

use App\Models\Product;
use App\Models\Service;

/**
 * Links what a customer asked about to the real item on the current
 * tenant's shelf. No match above the bar means "not in your catalog" —
 * the seed of "what they ask for and you do not offer".
 */
class CatalogMatcher
{
    /**
     * @param  list<float>  $vector
     * @return array{service_id: ?int, product_id: ?int}
     */
    public function match(array $vector): array
    {
        $bar = (float) config('atendia.analysis.catalog_similarity');
        $service = Service::closestTo($vector);
        $product = Product::closestTo($vector);

        $best = collect(['service_id' => $service, 'product_id' => $product])
            ->filter(fn (?array $item): bool => $item !== null && $item['similarity'] >= $bar)
            ->sortByDesc('similarity');

        return [
            'service_id' => $best->keys()->first() === 'service_id' ? $best->first()['id'] : null,
            'product_id' => $best->keys()->first() === 'product_id' ? $best->first()['id'] : null,
        ];
    }
}
