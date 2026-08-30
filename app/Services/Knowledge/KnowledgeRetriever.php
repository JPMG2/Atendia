<?php

declare(strict_types=1);

namespace App\Services\Knowledge;

use App\Dto\RetrievedChunkDto;
use App\Models\KnowledgeChunk;
use App\Services\Tenant;
use App\Traits\BelongsToBusiness;
use Illuminate\Support\Collection;

class KnowledgeRetriever
{
    public function __construct(
        private readonly KnowledgeEmbedder $embedder,
    ) {}

    /**
     * The `top_k` chunks closest to the query, ALWAYS bounded to one business.
     *
     * The isolation is not a `where` here: the business is adopted and the scope
     * of {@see BelongsToBusiness} applies it, so there is a single
     * source of truth. It also works on a queue or in the console, where a
     * forgotten filter would answer with another business's documents.
     *
     * @return Collection<int, RetrievedChunkDto>
     */
    public function retrieve(string $query, int $businessId, ?int $limit = null): Collection
    {
        $limit ??= (int) config('rag.retrieval.top_k');
        $vector = $this->embedder->embedOne($query);

        return app(Tenant::class)->for($businessId, fn (): Collection => KnowledgeChunk::query()
            ->select(['id', 'knowledge_document_id', 'business_id', 'content'])
            ->selectVectorDistance('embedding', $vector, as: 'distance')
            ->orderByVectorDistance('embedding', $vector)
            ->with('document:id,title')
            ->limit($limit)
            ->get()
            ->map(static fn (KnowledgeChunk $chunk): RetrievedChunkDto => new RetrievedChunkDto(
                documentId: (int) $chunk->knowledge_document_id,
                documentTitle: (string) ($chunk->document?->title ?? ''),
                content: (string) $chunk->content,
                distance: (float) $chunk->getAttribute('distance'),
            )));
    }

    /**
     * Builds a context block with [title] citations to ground the assistant.
     * Empty when that company has no knowledge yet.
     */
    public function context(string $query, int $businessId, ?int $limit = null): string
    {
        return $this->retrieve($query, $businessId, $limit)
            ->map(static fn (RetrievedChunkDto $chunk): string => "[{$chunk->documentTitle}] {$chunk->content}")
            ->implode("\n\n");
    }
}
