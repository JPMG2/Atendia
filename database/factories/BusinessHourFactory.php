<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Business;
use App\Models\BusinessHour;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BusinessHour>
 */
class BusinessHourFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'business_id' => Business::factory(),
            'day_of_week' => $this->faker->numberBetween(0, 6),
            'opens_at' => '09:00',
            'closes_at' => '18:00',
        ];
    }
}
