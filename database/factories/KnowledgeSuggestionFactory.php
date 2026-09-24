<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\QuestionResolution;
use App\Models\Business;
use App\Models\Conversation;
use App\Models\ConversationAnalysis;
use App\Models\ConversationQuestion;
use App\Models\KnowledgeSuggestion;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<KnowledgeSuggestion>
 */
class KnowledgeSuggestionFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'business_id' => Business::factory(),
            'question' => '¿'.rtrim(fake()->sentence(4), '.').'?',
        ];
    }

    /** Asked once in the given thread, as the conversation analysis leaves it. */
    public function askedIn(Conversation $thread, ?string $teamAnswer = null): static
    {
        return $this->afterCreating(function (KnowledgeSuggestion $suggestion) use ($thread, $teamAnswer): void {
            $analysis = ConversationAnalysis::query()->create([
                'business_id' => $suggestion->business_id,
                'conversation_id' => $thread->id,
                'first_message_id' => 1,
                'last_message_id' => 1,
                'sentiment' => 'neutral',
            ]);

            ConversationQuestion::query()->create([
                'business_id' => $suggestion->business_id,
                'conversation_id' => $thread->id,
                'conversation_analysis_id' => $analysis->id,
                'question_intent_id' => $suggestion->question_intent_id,
                'question' => $suggestion->question,
                'resolved_by' => $teamAnswer !== null ? QuestionResolution::Team : QuestionResolution::Nobody,
                'answer' => $teamAnswer,
                'knowledge_suggestion_id' => $suggestion->id,
            ]);
        });
    }
}
