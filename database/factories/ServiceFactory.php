<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Business;
use App\Models\Service;
use App\Models\ServiceType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Service>
 */
class ServiceFactory extends Factory
{
    /**
     * Price, length and description stay null on purpose: the bare name is the
     * floor a real service starts from, so the default state mirrors it.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'business_id' => Business::factory(),
            'service_type_id' => ServiceType::factory(),
            'name' => ucfirst($this->faker->unique()->words(2, true)),
            'is_active' => true,
        ];
    }
}
