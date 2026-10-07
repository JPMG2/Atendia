<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\MessageAuthor;
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
        // Authored by the assistant, which is what an outgoing message IS
        // unless a person took the thread: in the live table 8 of the 11
        // outgoing rows say `assistant`, and a null author is pre-column.
        return $this->state(fn (): array => [
            'direction' => MessageDirection::Out,
            'author' => MessageAuthor::Assistant,
        ]);
    }

    public function byHuman(): static
    {
        return $this->state(fn (): array => [
            'direction' => MessageDirection::Out,
            'author' => MessageAuthor::Human,
        ]);
    }
}
