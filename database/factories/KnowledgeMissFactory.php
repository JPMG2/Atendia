<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Business;
use App\Models\KnowledgeMiss;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<KnowledgeMiss>
 */
class KnowledgeMissFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'business_id' => Business::factory(),
            'query' => '¿'.fake()->sentence(4).'?',
        ];
    }
}
