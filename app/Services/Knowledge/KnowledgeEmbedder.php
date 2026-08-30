<?php

declare(strict_types=1);

namespace App\Services\Knowledge;

use Laravel\Ai\Embeddings;
use Laravel\Ai\Enums\Lab;

class KnowledgeEmbedder
{
    /**
     * Embeds text with the SAME model and dimensions as the `vector` column.
     * Keeping it in one place is what guarantees indexing and querying share a
     * vector space — otherwise similarity means nothing.
     *
     * @param  list<string>  $texts
     * @return list<list<float>>
     */
    public function embed(array $texts): array
    {
        if ($texts === []) {
            return [];
        }

        return Embeddings::for(array_values($texts))
            ->dimensions((int) config('rag.embedding.dimensions'))
            ->generate(Lab::OpenAI, (string) config('rag.embedding.model'))
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
