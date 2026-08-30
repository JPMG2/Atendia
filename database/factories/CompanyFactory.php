<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Company;
use App\Models\Region;
use App\Models\TaxCondition;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Company>
 */
class CompanyFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'legal_name' => $this->faker->company(),
            'tax_id' => (string) $this->faker->unique()->numberBetween(20000000000, 29999999999),
            // The FK is required on the table; the tax standing is not.
            'region_id' => Region::factory(),
            'tax_condition_id' => TaxCondition::factory(),
            'address' => $this->faker->streetAddress(),
            'email' => $this->faker->companyEmail(),
            'phone' => $this->faker->phoneNumber(),
            'web' => $this->faker->url(),
            'text_copyright' => $this->faker->sentence(),
            'tagline' => $this->faker->catchPhrase(),
        ];
    }
}
