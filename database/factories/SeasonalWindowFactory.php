<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\SeasonalWindow;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SeasonalWindow>
 */
class SeasonalWindowFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => 'Temporada '.$this->faker->unique()->word(),
            'starts_at' => SeasonalWindow::today(),
            'ends_at' => SeasonalWindow::today()->addDays(7),
            'priority' => 0,
            'is_active' => true,
        ];
    }

    /** Already over: what the landing must ignore. */
    public function past(): static
    {
        return $this->state(fn (): array => [
            'starts_at' => SeasonalWindow::today()->subDays(30),
            'ends_at' => SeasonalWindow::today()->subDay(),
        ]);
    }
}
