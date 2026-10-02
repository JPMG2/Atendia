<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\HelpArticle;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<HelpArticle>
 */
class HelpArticleFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $title = fake()->sentence(6);

        return [
            'slug' => Str::slug($title),
            'category' => 'whatsapp',
            'screen' => 'whatsapp',
            'title' => $title,
            'body' => fake()->paragraph(),
            'sort_order' => 0,
            'is_active' => true,
        ];
    }
}
