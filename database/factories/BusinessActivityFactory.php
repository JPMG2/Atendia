<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\BusinessActivity;
use App\Models\BusinessSector;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BusinessActivity>
 */
class BusinessActivityFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'business_sector_id' => BusinessSector::factory(),
            // `code` is globally unique; `name` is unique within the sector.
            'code' => $this->faker->unique()->lexify('actividad-????'),
            'name' => $this->faker->unique()->words(2, true),
            'description' => $this->faker->sentence(),
            'sort_order' => 0,
            'is_active' => true,
        ];
    }
}
