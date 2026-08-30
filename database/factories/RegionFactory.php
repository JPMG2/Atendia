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
            // Unique within the province: the same name may repeat across provinces
            // but not inside one.

            // The number comes from a large space on purpose: chaining
            // `unique()->citySuffix()` drained faker's pool and the factory threw an
            // OverflowException, which reads in a test like a bug in the code.
            'name' => 'Zona '.$this->faker->unique()->numberBetween(1, 100000),
            'is_active' => true,
        ];
    }
}
