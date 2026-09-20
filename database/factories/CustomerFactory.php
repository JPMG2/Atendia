<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Business;
use App\Models\Customer;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Customer>
 */
class CustomerFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'business_id' => Business::factory(),
            'phone' => fake()->numerify('549#########'),
            'profile_name' => fake()->firstName(),
            'first_seen_at' => now(),
        ];
    }
}
