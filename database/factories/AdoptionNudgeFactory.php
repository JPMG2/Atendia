<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\AdoptionStep;
use App\Models\AdoptionNudge;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AdoptionNudge>
 */
class AdoptionNudgeFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'step' => AdoptionStep::Registered,
            'subject' => $this->faker->sentence(4),
            'body' => $this->faker->sentence(14),
            'is_active' => true,
        ];
    }
}
