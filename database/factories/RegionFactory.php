<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Province;
use App\Models\Region;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Region>
 */
class RegionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'province_id' => Province::factory(),
            // Único dentro de la provincia: el mismo nombre puede repetirse
            // entre provincias distintas, pero no dentro de una.
            'name' => $this->faker->unique()->citySuffix().' '.$this->faker->unique()->numberBetween(1, 9999),
            'is_active' => true,
        ];
    }
}
