<?php

declare(strict_types=1);

namespace App\Ai\Tools;

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
        $context = app(KnowledgeRetriever::class)
            ->context((string) $request['query'], $this->businessId);

        // Said out loud, so the model answers "I could not confirm it" instead
        // of improvising from an empty context.
        return $context === ''
            ? 'No se encontró información sobre eso en la base de conocimiento del negocio.'
            : $context;
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
