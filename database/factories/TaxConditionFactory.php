<?php

namespace Database\Factories;

use App\Models\Country;
use App\Models\TaxCondition;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TaxCondition>
 */
class TaxConditionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'country_id' => Country::factory(),
            'name' => $this->faker->unique()->words(2, true),
            'code' => strtoupper($this->faker->unique()->lexify('??')),
            'discriminate_tax' => $this->faker->boolean(),
            'is_active' => true,
        ];
    }
}
