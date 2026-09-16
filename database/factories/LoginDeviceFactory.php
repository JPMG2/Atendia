<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\LoginDevice;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LoginDevice>
 */
class LoginDeviceFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $userAgent = $this->faker->userAgent();

        return [
            'user_id' => User::factory(),
            'fingerprint' => LoginDevice::fingerprintFor($userAgent),
            'ip' => $this->faker->ipv4(),
            'user_agent' => $userAgent,
            'last_login_at' => now(),
        ];
    }
}
