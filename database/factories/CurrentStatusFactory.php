<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\CurrentStatus;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CurrentStatus>
 */
class CurrentStatusFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            // `name` is UNIQUE on the table, so it has to be unique here too or two
            // statuses built in one test blow up against the constraint.
            'name' => $this->faker->unique()->word(),
            // A key from CurrentStatus::COLORS; a free value the CSS cannot paint.
            'color' => $this->faker->randomElement(CurrentStatus::COLORS),
        ];
    }
}
