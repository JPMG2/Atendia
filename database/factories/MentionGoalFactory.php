<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\MentionGoal;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MentionGoal>
 */
class MentionGoalFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'threshold' => $this->faker->unique()->numberBetween(10, 100000),
            'caption' => '{negocio} alcanzó {logro}.',
            'is_active' => true,
        ];
    }
}
