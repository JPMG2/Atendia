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
            // `(country_id, name)` se valida como único por país, así que dos
            // provincias del mismo país no pueden compartir nombre.
            'name' => $this->faker->unique()->city(),
            'is_active' => true,
        ];
    }
}
