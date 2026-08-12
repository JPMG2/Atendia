<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Company;
use App\Models\KnowledgeDocument;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<KnowledgeDocument>
 */
class KnowledgeDocumentFactory extends Factory
{
    protected $model = KnowledgeDocument::class;

    /**
     * `status` y `content_hash` los completa el observer al guardar; no se setean acá.
     */
    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'title' => fake()->unique()->sentence(3),
            'source_type' => 'manual',
            'content' => fake()->paragraphs(2, true),
        ];
    }
}
