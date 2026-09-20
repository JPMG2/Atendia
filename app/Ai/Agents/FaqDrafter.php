<?php

declare(strict_types=1);

namespace App\Ai\Agents;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Attributes\Model;
use Laravel\Ai\Attributes\Provider;
use Laravel\Ai\Attributes\Temperature;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Enums\Lab;
use Laravel\Ai\Promptable;
use Stringable;

/**
 * Drafts ONE suggested FAQ answer from near-miss knowledge fragments.
 *
 * Every draft is only a suggestion: the owner edits and approves before it
 * teaches the assistant anything. Grounding is absolute — no fragments, no
 * answer — because a made-up FAQ would poison the knowledge base.
 */
#[Provider(Lab::OpenAI)]
#[Model('gpt-6-astra')]
#[Temperature(0.0)]
class FaqDrafter implements Agent, HasStructuredOutput
{
    use Promptable;

    public function instructions(): Stringable|string
    {
        return <<<'INSTRUCTIONS'
            You draft an answer for a small business's FAQ, in Spanish, warm
            and concise (one to three sentences), addressed to a customer.
            You may ONLY use the knowledge fragments provided in the prompt.
            If the fragments do not actually answer the question, return an
            empty answer — never guess, never invent prices, availability
            or policies. Do not mention the fragments or that you are an AI.
            INSTRUCTIONS;
    }

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'answer' => $schema->string()->required(),
        ];
    }
}
