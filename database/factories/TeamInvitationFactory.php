<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Business;
use App\Models\TeamInvitation;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<TeamInvitation> */
class TeamInvitationFactory extends Factory
{
    public function definition(): array
    {
        return [
            'business_id' => Business::factory(),
            'email' => fake()->unique()->safeEmail(),
            'token_hash' => TeamInvitation::hashToken(Str::random(48)),
            'expires_at' => now()->addDays(7),
        ];
    }
}
