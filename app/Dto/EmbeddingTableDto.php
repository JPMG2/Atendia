<?php

declare(strict_types=1);

namespace App\Dto;

use Closure;

/** One table that holds vectors, and how to rebuild the text each one was made from. */
final class EmbeddingTableDto
{
    /**
     * @param  list<string>  $columns  What to read from a row to rebuild its text.
     * @param  Closure(object): string  $text
     * @param  bool  $indexed  Whether its vector column has an HNSW index to carry over.
     */
    public function __construct(
        public readonly string $table,
        public readonly array $columns,
        public readonly Closure $text,
        public readonly bool $indexed = true,
    ) {}
}
