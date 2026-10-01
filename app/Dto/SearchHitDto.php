<?php

declare(strict_types=1);

namespace App\Dto;

/**
 * One row of the global search. `rank` is the position the source gave it
 * inside its own lane; the fusion reads positions, never raw scores, because
 * a lexical score and a cosine distance are not comparable numbers.
 */
final class SearchHitDto
{
    public function __construct(
        public readonly string $group,
        public readonly string $icon,
        public readonly string $title,
        public readonly string $subtitle,
        public readonly string $url,
        public readonly string $key,
        public readonly int $rank = 0,
        public readonly bool $semantic = false,
    ) {}

    /** The same row found by both lanes keeps one entry; this is its identity. */
    public static function keyFor(string $group, int|string $id): string
    {
        return $group.':'.$id;
    }

    public function atRank(int $rank): self
    {
        return new self(
            group: $this->group,
            icon: $this->icon,
            title: $this->title,
            subtitle: $this->subtitle,
            url: $this->url,
            key: $this->key,
            rank: $rank,
            semantic: $this->semantic,
        );
    }
}
