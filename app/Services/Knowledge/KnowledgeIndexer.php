<?php

declare(strict_types=1);

namespace App\Services\Knowledge;

use App\Models\KnowledgeDocument;
use Illuminate\Support\Facades\DB;

class KnowledgeIndexer
{
    public function __construct(
        private readonly KnowledgeChunker $chunker,
        private readonly KnowledgeEmbedder $embedder,
    ) {}

    /**
     * (Re)indexa un documento: borra sus fragmentos viejos, re-fragmenta, embeddea
     * en batch y persiste. Idempotente: correrlo dos veces deja el mismo resultado.
     */
    public function index(KnowledgeDocument $document): void
    {
        $pieces = $this->chunker->chunk((string) $document->content);

        DB::transaction(function () use ($document, $pieces): void {
            $document->chunks()->delete();

            if ($pieces !== []) {
                $vectors = $this->embedder->embed(array_column($pieces, 'content'));

                foreach ($pieces as $i => $piece) {
                    $document->chunks()->create([
                        'business_id' => $document->business_id,
                        'chunk_index' => $i,
                        'content' => $piece['content'],
                        'token_count' => $piece['token_count'],
                        'embedding' => $vectors[$i],
                    ]);
                }
            }

            $document->forceFill([
                'status' => 'indexed',
                'indexed_at' => now(),
            ])->save();
        });
    }
}
