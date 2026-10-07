<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\CustomerSentiment;
use App\Models\Business;
use App\Models\Conversation;
use App\Models\ConversationAnalysis;
use App\Models\ConversationMessage;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ConversationAnalysis>
 */
class ConversationAnalysisFactory extends Factory
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
            ])->id,
            // A reading always covers a real stretch: both ends are NOT NULL,
            // and the shortest honest stretch is one message.
            'first_message_id' => fn (array $attributes) => ConversationMessage::factory()->create([
                'business_id' => $attributes['business_id'],
                'conversation_id' => $attributes['conversation_id'],
            ])->id,
            'last_message_id' => fn (array $attributes) => $attributes['first_message_id'],
            'sentiment' => CustomerSentiment::Neutral,
        ];
    }

    public function negative(): static
    {
        return $this->state(fn (): array => ['sentiment' => CustomerSentiment::Negative]);
    }
}
