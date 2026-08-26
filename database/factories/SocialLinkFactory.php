<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Company;
use App\Models\SocialLink;
use App\Models\SocialNetwork;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SocialLink>
 */
class SocialLinkFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'social_network_id' => SocialNetwork::factory(),
            // The owner is polymorphic: the company is only the default, any test
            // can hand it a business with `for($business, 'linkable')`.
            'linkable_id' => Company::factory(),
            'linkable_type' => Company::class,
            'url' => 'https://www.'.$this->faker->unique()->domainWord().'.com/atendia',
            'sort_order' => 0,
        ];
    }
}
