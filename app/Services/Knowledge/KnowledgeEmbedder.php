<?php

declare(strict_types=1);

namespace App\Services\Knowledge;

use App\Classes\Main\EmbeddingSpace;
use Laravel\Ai\Embeddings;

class KnowledgeEmbedder
{
    /**
     * Embeds text in the space in force: the SAME model and length as the
     * `vector` column. Keeping it in one place is what guarantees indexing and
     * querying share a vector space — otherwise similarity means nothing. A
     * change of model passes its own space to make the vectors that will rule.
     *
     * @param  list<string>  $texts
     * @return list<list<float>>
     */
    public function embed(array $texts, ?EmbeddingSpace $space = null): array
    {
        if ($texts === []) {
            return [];
        }

        $space ??= EmbeddingSpace::active();

        return Embeddings::for(array_values($texts))
            ->dimensions($space->dimensions)
            ->generate($space->lab, $space->model)
            ->embeddings;
    }

    /**
     * @return list<float>
     */
    public function embedOne(string $text): array
    {
        return $this->embed([$text])[0];
    }
}
