<?php

namespace Database\Factories;

use App\Models\Country;
use App\Models\Currency;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Country>
 */
class CountryFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'currency_id' => Currency::factory(),
            'name' => $this->faker->unique()->country(),
            'code' => strtoupper($this->faker->unique()->lexify('???')),
            'phone_code' => (string) $this->faker->numberBetween(1, 999),
            'is_active' => true,
        ];
    }
}
