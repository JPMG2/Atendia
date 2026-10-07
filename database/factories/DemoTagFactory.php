<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\DemoTag;
use App\Models\SeasonalWindow;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DemoTag>
 */
class DemoTagFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $noun = $this->faker->unique()->word();

        return [
            'slug' => $noun,
            'seasonal_window_id' => null,
            'label' => ucfirst($noun),
            'business_name' => ucfirst($noun).' '.$this->faker->lastName(),
            'noun' => $noun,
            'chips' => [$this->faker->sentence(), $this->faker->sentence()],
            'pool' => [
                ['side' => 'in', 'text' => $this->faker->sentence()],
                ['side' => 'out', 'text' => $this->faker->sentence()],
            ],
            'sort_order' => 10,
            'is_active' => true,
        ];
    }

    /** A variant of an existing tag: same slug, inside a season. */
    public function variantOf(DemoTag $tag, SeasonalWindow $window): static
    {
        return $this->state(fn (): array => [
            'slug' => $tag->slug,
            'seasonal_window_id' => $window->id,
            'label' => null,
            'business_name' => null,
            'noun' => null,
            'chips' => null,
            'pool' => null,
        ]);
    }
}
