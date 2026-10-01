<?php

declare(strict_types=1);

namespace App\Classes\Search;

use App\Dto\SearchHitDto;
use App\Interfaces\Main\SearchSource;
use App\Models\Product;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * The tenant's products. The words lane carries the code, which the meaning
 * lane cannot: an SKU has no neighbourhood in a vector space.
 */
class ProductSource implements SearchSource
{
    public string $group {
        get => 'search.groups.products';
    }

    public string $icon {
        get => 'package';
    }

    public int $order {
        get => 2;
    }

    public function byWords(string $term, int $limit): Collection
    {
        return Product::matching($term, $limit)
            ->map(fn (Product $product): SearchHitDto => $this->hit(
                (int) $product->id,
                (string) $product->name,
                (string) ($product->code ?? ''),
                (string) ($product->description ?? ''),
            ))
            ->values();
    }

    public function byMeaning(array $vector, int $limit): Collection
    {
        return collect(Product::closestMany($vector, $limit))
            ->map(fn (array $row): SearchHitDto => $this->hit(
                $row['id'], $row['name'], '', '', semantic: true,
            ));
    }

    private function hit(int $id, string $name, string $code, string $description, bool $semantic = false): SearchHitDto
    {
        $subtitle = $code !== '' ? $code : Str::limit($description, 70);

        return new SearchHitDto(
            group: $this->group,
            icon: $this->icon,
            title: $name,
            subtitle: $subtitle !== '' ? $subtitle : __('search.subtitles.product'),
            url: route('my-products', ['buscar' => $name]),
            key: SearchHitDto::keyFor($this->group, $id),
            semantic: $semantic,
        );
    }
}
