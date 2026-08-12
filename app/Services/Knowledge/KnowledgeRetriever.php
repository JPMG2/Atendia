<?php

declare(strict_types=1);

namespace App\Services\Knowledge;

use App\Dto\RetrievedChunkDto;
use App\Models\KnowledgeChunk;
use Illuminate\Support\Collection;

class KnowledgeRetriever
{
    public function __construct(
        private readonly KnowledgeEmbedder $embedder,
    ) {}

    /**
     * Recupera los `top_k` fragmentos más parecidos a la consulta, SIEMPRE
     * acotados a una empresa (multi-tenant: jamás cruza `company_id`). Embeddea
     * la consulta a mano para usar el mismo modelo/dims que el indexado.
     *
     * @return Collection<int, RetrievedChunkDto>
     */
    public function retrieve(string $query, int $companyId, ?int $limit = null): Collection
    {
        $limit ??= (int) config('rag.retrieval.top_k');
        $vector = $this->embedder->embedOne($query);

        return KnowledgeChunk::query()
            ->where('company_id', $companyId)
            ->select(['id', 'knowledge_document_id', 'company_id', 'content'])
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
            ));
    }

    /**
     * Arma un bloque de contexto con citas [título] para pasarle al asistente
     * como grounding. Vacío si no hay conocimiento para esa empresa.
     */
    public function context(string $query, int $companyId, ?int $limit = null): string
    {
        return $this->retrieve($query, $companyId, $limit)
            ->map(static fn (RetrievedChunkDto $chunk): string => "[{$chunk->documentTitle}] {$chunk->content}")
            ->implode("\n\n");
    }
}
