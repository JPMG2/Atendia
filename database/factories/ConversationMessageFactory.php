<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\MessageDirection;
use App\Models\Business;
use App\Models\Conversation;
use App\Models\ConversationMessage;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ConversationMessage>
 */
class ConversationMessageFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'business_id' => Business::factory(),
            // Same business on both rows: RLS fences each table by its own column.
            'conversation_id' => fn (array $attributes) => Conversation::factory()->create([
                'business_id' => $attributes['business_id'],
            ]),
            'direction' => MessageDirection::In,
            'body' => fake()->sentence(),
        ];
    }

    public function out(): static
    {
        return $this->state(fn (): array => ['direction' => MessageDirection::Out]);
    }
}
