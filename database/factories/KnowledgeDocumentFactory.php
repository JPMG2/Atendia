<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Business;
use App\Models\KnowledgeDocument;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<KnowledgeDocument>
 */
class KnowledgeDocumentFactory extends Factory
{
    protected $model = KnowledgeDocument::class;

    /**
     * `status` and `content_hash` are filled by the observer on save, not here.
     */
    public function definition(): array
    {
        return [
            'business_id' => Business::factory(),
            'title' => fake()->unique()->sentence(3),
            'source_type' => 'manual',
            'content' => fake()->paragraphs(2, true),
        ];
    }
}
