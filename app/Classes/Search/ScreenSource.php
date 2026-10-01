<?php

declare(strict_types=1);

namespace App\Classes\Search;

use App\Dto\SearchHitDto;
use App\Interfaces\Main\SearchSource;
use App\Models\Menu;
use Illuminate\Support\Collection;

/**
 * The panel's own screens. First group on purpose: "take me there" is the
 * most common thing asked of a palette, and it costs no query at all —
 * Menu::tree() is already in memory and already filtered by permission.
 */
class ScreenSource implements SearchSource
{
    public string $group {
        get => 'search.groups.screens';
    }

    public string $icon {
        get => 'compass';
    }

    public int $order {
        get => 0;
    }

    public function byWords(string $term, int $limit): Collection
    {
        $needle = Accents::fold(mb_strtolower(trim($term)));

        return $this->screens()
            ->filter(fn (Menu $item): bool => str_contains(
                Accents::fold(mb_strtolower($item->label)), $needle,
            ))
            ->take($limit)
            ->values()
            ->map(fn (Menu $item): SearchHitDto => new SearchHitDto(
                group: $this->group,
                icon: $item->icon ?? $this->icon,
                title: $item->label,
                subtitle: __('search.go_to_screen'),
                url: (string) $item->url,
                key: SearchHitDto::keyFor($this->group, $item->id),
            ));
    }

    /** Screens have no vectors: a menu is a dozen labels, not a corpus. */
    public function byMeaning(array $vector, int $limit): Collection
    {
        return collect();
    }

    /** The tree flattened, keeping only the nodes that lead somewhere. */
    private function screens(): Collection
    {
        return $this->flatten(Menu::tree())
            ->filter(fn (Menu $item): bool => $item->route_name !== null && $item->url !== null)
            ->unique('id');
    }

    /**
     * @param  Collection<int, Menu>  $items
     * @return Collection<int, Menu>
     */
    private function flatten(Collection $items): Collection
    {
        return $items->flatMap(fn (Menu $item): Collection => collect([$item])
            ->merge($this->flatten($item->childrenRecursive)));
    }
}
