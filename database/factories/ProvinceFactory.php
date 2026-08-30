<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Country;
use App\Models\Province;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Province>
 */
class ProvinceFactory extends Factory
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
            // `(country_id, name)` is validated as unique per country, so two
            // provinces of one country cannot share a name.
            'name' => $this->faker->unique()->city(),
            'is_active' => true,
        ];
    }
}
