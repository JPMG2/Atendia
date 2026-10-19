<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\SocialFollowerCount;
use App\Models\SocialLink;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SocialFollowerCount>
 */
class SocialFollowerCountFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'social_link_id' => SocialLink::factory(),
            'followers' => $this->faker->numberBetween(10, 5000),
            'counted_on' => today(),
        ];
    }
}
