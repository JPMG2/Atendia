<?php

declare(strict_types=1);

namespace App\Classes\Search;

use App\Dto\SearchHitDto;
use App\Interfaces\Main\SearchSource;
use App\Models\HelpArticle;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * AtendIa's own answers, reachable from any screen. Last group on purpose: she
 * is usually looking for her own data, and only sometimes for how something
 * works — but when she is, walking to the menu first is a step too many.
 */
class HelpSource implements SearchSource
{
    public string $group {
        get => 'search.groups.help';
    }

    public string $icon {
        get => 'life-buoy';
    }

    public int $order {
        get => 6;
    }

    public function byWords(string $term, int $limit): Collection
    {
        return HelpArticle::searchAll($term, $limit)
            ->map(fn (HelpArticle $article): SearchHitDto => new SearchHitDto(
                group: $this->group,
                icon: $this->icon,
                title: $article->title,
                subtitle: Str::limit(trim((string) preg_replace('/\s+/', ' ', $article->body)), 70),
                url: route('help', ['buscar' => $article->title]),
                key: SearchHitDto::keyFor($this->group, $article->id),
            ))
            ->values();
    }

    /**
     * The vectors exist, but the palette must not spend one per keystroke:
     * the help screen pays for meaning, this lane answers with words.
     */
    public function byMeaning(array $vector, int $limit): Collection
    {
        return collect();
    }
}
