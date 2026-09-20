<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\PlatformContact;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PlatformContact>
 */
class PlatformContactFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'phone' => fake()->numerify('549#########'),
            'first_seen_at' => now(),
        ];
    }
}
