<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\SupportReply;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SupportReply>
 */
class SupportReplyFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            // UNIQUE on the table, so unique here too.
            'name' => $this->faker->unique()->sentence(3),
            'body' => $this->faker->sentence(12),
            'is_active' => true,
        ];
    }
}
