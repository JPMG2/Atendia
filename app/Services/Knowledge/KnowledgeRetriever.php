<?php

declare(strict_types=1);

namespace App\Services\Knowledge;

use App\Dto\RetrievedChunkDto;
use App\Services\Tenant;
use App\Models\KnowledgeChunk;
use Illuminate\Support\Collection;

class KnowledgeRetriever
{
    public function __construct(
        private readonly KnowledgeEmbedder $embedder,
    ) {}

    /**
     * Recupera los `top_k` fragmentos más parecidos a la consulta, SIEMPRE
     * acotados a un negocio (multi-tenant: jamás cruza `business_id`). Embeddea
     * la consulta a mano para usar el mismo modelo/dims que el indexado.
     *
     * El aislamiento NO se hace acá con un `where`: se adopta el negocio y lo
     * aplica el scope de {@see \App\Traits\BelongsToBusiness}. Una sola fuente
     * de verdad. Además funciona en cola o en consola, donde no hay sesión y un
     * filtro olvidado dejaría que la IA responda con documentos de otro negocio.
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
     * Arma un bloque de contexto con citas [título] para pasarle al asistente
     * como grounding. Vacío si no hay conocimiento para esa empresa.
     */
    public function context(string $query, int $businessId, ?int $limit = null): string
    {
        return $this->retrieve($query, $businessId, $limit)
            ->map(static fn (RetrievedChunkDto $chunk): string => "[{$chunk->documentTitle}] {$chunk->content}")
            ->implode("\n\n");
    }
}
