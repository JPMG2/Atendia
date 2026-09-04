<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Business;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    /**
     * Price, stock and description stay null on purpose: the bare name is the
     * floor a real product starts from, so the default state mirrors it.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'business_id' => Business::factory(),
            'name' => ucfirst($this->faker->unique()->words(2, true)),
            'is_active' => true,
        ];
    }
}
