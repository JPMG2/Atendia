<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\BusinessActivity;
use App\Models\Specialty;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Specialty>
 */
class SpecialtyFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'business_activity_id' => BusinessActivity::factory(),
            // `code` is globally unique; `name` is unique within the activity.
            'code' => $this->faker->unique()->lexify('especialidad-????'),
            'name' => $this->faker->unique()->words(2, true),
            'sort_order' => 0,
            'is_active' => true,
        ];
    }
}
