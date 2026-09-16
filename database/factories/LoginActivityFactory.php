<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\LoginActivity;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LoginActivity>
 */
class LoginActivityFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'ip' => $this->faker->ipv4(),
            'location' => null,
            'user_agent' => $this->faker->userAgent(),
        ];
    }
}
