<?php

declare(strict_types=1);

namespace App\Services\Knowledge;

use Laravel\Ai\Embeddings;
use Laravel\Ai\Enums\Lab;

class KnowledgeEmbedder
{
    /**
     * Embeddea uno o más textos con el MISMO modelo/dimensiones que la columna
     * `vector` (config/rag.php). Centralizarlo garantiza que indexado y consulta
     * usen el mismo espacio vectorial; si no, la similitud no significa nada.
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
