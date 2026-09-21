<?php

declare(strict_types=1);

namespace App\Ai\Tools;

use App\Dto\RetrievedChunkDto;
use App\Models\KnowledgeMiss;
use App\Services\Knowledge\KnowledgeRetriever;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

/**
 * The assistant's eyes into ONE business's knowledge base.
 *
 * The business is pinned at construction and never taken from the model: an
 * argument the model writes could point the search at another tenant. The
 * retriever adopts the business, so the isolation scope does the rest.
 */
class SearchBusinessKnowledge implements Tool
{
    public function __construct(
        private readonly int $businessId,
    ) {}

    /** @var array<int, string> document id => title, every search of this exchange */
    private array $sourcesById = [];

    /**
     * The documents that grounded this exchange's answer, for the panel's
     * "¿de dónde salió?" trail.
     *
     * @return list<array{id: int, title: string}>
     */
    public function sources(): array
    {
        return collect($this->sourcesById)
            ->map(fn (string $title, int $id): array => ['id' => $id, 'title' => $title])
            ->values()
            ->all();
    }

    /**
     * Get the description of the tool's purpose.
     */
    public function description(): Stringable|string
    {
        return 'Busca en la base de conocimiento del negocio (servicios, inventario, '
            .'listas de precios y documentos importados) la información relevante a la '
            .'consulta del cliente. Usala SIEMPRE antes de afirmar o negar que el '
            .'negocio ofrece algo.';
    }

    /**
     * Execute the tool.
     */
    public function handle(Request $request): Stringable|string
    {
        $query = (string) $request['query'];

        $chunks = app(KnowledgeRetriever::class)
            ->retrieve($query, $this->businessId);

        if ($chunks->isEmpty()) {
            // The exact moment a question goes unanswered: logged here, at
            // the source, so the owner's teaching queue never guesses.
            KnowledgeMiss::query()->create(['business_id' => $this->businessId, 'query' => mb_substr($query, 0, 500)]);

            // Said out loud, so the model answers "I could not confirm it"
            // instead of improvising from an empty context.
            return 'No se encontró información sobre eso en la base de conocimiento del negocio.';
        }

        // Several chunks may share one document; the trail keeps each once.
        foreach ($chunks as $chunk) {
            $this->sourcesById[$chunk->documentId] ??= $chunk->documentTitle;
        }

        return $chunks
            ->map(static fn (RetrievedChunkDto $chunk): string => "[{$chunk->documentTitle}] {$chunk->content}")
            ->implode("\n\n");
    }

    /**
     * Get the tool's schema definition.
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'query' => $schema->string()->required(),
        ];
    }
}
