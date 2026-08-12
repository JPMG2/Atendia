<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Business;
use App\Models\Country;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Business>
 */
class BusinessFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->company(),
            // El país se declara al registrarse, así que nunca puede faltar.
            'country_id' => Country::query()->inRandomOrder()->value('id') ?? Country::factory(),
            'billing_email' => fake()->unique()->safeEmail(),
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (): array => ['is_active' => false]);
    }
}
