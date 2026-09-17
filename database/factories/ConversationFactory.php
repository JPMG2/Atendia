<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Business;
use App\Models\Conversation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Conversation>
 */
class ConversationFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'business_id' => Business::factory(),
            'contact_phone' => fake()->numerify('549#########'),
            'contact_name' => fake()->firstName(),
            'last_message_at' => now(),
        ];
    }
}
