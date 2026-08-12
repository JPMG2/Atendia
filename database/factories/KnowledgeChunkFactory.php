<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Business;
use App\Models\KnowledgeChunk;
use App\Models\KnowledgeDocument;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<KnowledgeChunk>
 */
class KnowledgeChunkFactory extends Factory
{
    protected $model = KnowledgeChunk::class;

    public function definition(): array
    {
        return [
            'knowledge_document_id' => KnowledgeDocument::factory(),
            'business_id' => Business::factory(),
            'chunk_index' => 0,
            'content' => fake()->paragraph(),
            'token_count' => fake()->numberBetween(10, 200),
            'embedding' => self::randomVector((int) config('rag.embedding.dimensions')),
        ];
    }

    /**
     * Ata el fragmento a un documento y hereda su empresa (coherencia de tenant).
     */
    public function forDocument(KnowledgeDocument $document): static
    {
        return $this->state([
            'knowledge_document_id' => $document->id,
            'business_id' => $document->business_id,
        ]);
    }

    /**
     * @param  list<float>  $vector
     */
    public function withEmbedding(array $vector): static
    {
        return $this->state(['embedding' => $vector]);
    }

    /**
     * @return list<float>
     */
    private static function randomVector(int $dimensions): array
    {
        return array_map(
            static fn (): float => round(random_int(-1000, 1000) / 1000, 4),
            range(1, $dimensions),
        );
    }
}
