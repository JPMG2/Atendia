<?php

declare(strict_types=1);

namespace Tests\Support\AiEval;

use App\Services\Knowledge\KnowledgeEmbedder;

/**
 * Real embeddings, paid once per distinct text across the whole battery:
 * every case rebuilds its business, and re-embedding the same catalog 70
 * times would cost minutes and tokens for nothing.
 */
class CachedEmbedder extends KnowledgeEmbedder
{
    /** @var array<string, list<float>> */
    private static array $vectors = [];

    public function embed(array $texts): array
    {
        $missing = array_values(array_unique(array_filter($texts, fn (string $text): bool => ! isset(self::$vectors[$text]))));

        foreach (array_map(null, $missing, parent::embed($missing)) as [$text, $vector]) {
            if ($text !== null) {
                self::$vectors[$text] = $vector;
            }
        }

        return array_map(fn (string $text): array => self::$vectors[$text], array_values($texts));
    }
}
