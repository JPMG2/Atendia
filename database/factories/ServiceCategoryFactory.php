<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Business;
use App\Models\ServiceCategory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ServiceCategory>
 */
class ServiceCategoryFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'business_id' => Business::factory(),
            'name' => ucfirst($this->faker->unique()->words(2, true)),
            'sort_order' => 0,
        ];
    }
}
