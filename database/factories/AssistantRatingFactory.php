<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\AssistantRating;
use App\Models\Business;
use App\Models\ConversationMessage;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AssistantRating>
 */
class AssistantRatingFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'business_id' => Business::factory(),
            // Same business on both rows: RLS fences each table by its own column.
            'conversation_message_id' => fn (array $attributes) => ConversationMessage::factory()->out()->create([
                'business_id' => $attributes['business_id'],
            ])->id,
            'is_good' => true,
        ];
    }

    public function bad(): static
    {
        return $this->state(fn (): array => ['is_good' => false]);
    }
}
