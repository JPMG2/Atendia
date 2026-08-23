<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\ServiceModality;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ServiceModality>
 */
class ServiceModalityFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        // `code` y `name` son únicos globales en la tabla.
        return [
            'code' => $this->faker->unique()->lexify('modalidad-????'),
            'name' => $this->faker->unique()->words(2, true),
            'description' => $this->faker->sentence(),
            'icon' => 'zap',
            'sort_order' => 0,
            'is_active' => true,
        ];
    }
}
