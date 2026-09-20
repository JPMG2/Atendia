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
 * Carries the human's Spanish reply into the customer's language: the team
 * always writes in Spanish, the customer always reads their own tongue
 * (the owner's decision, 2026-09-10).
 */
#[Provider(Lab::OpenAI)]
#[Model('gpt-6-astra')]
#[Temperature(0.0)]
class ReplyTranslator implements Agent, HasStructuredOutput
{
    use Promptable;

    public function instructions(): Stringable|string
    {
        return <<<'INSTRUCTIONS'
            You translate a business team's reply, written in Spanish, into
            the customer's language given in the prompt. Keep the meaning,
            warmth and brevity; translate ONLY — never add, remove or answer
            anything yourself. Return the translation as "text".
            INSTRUCTIONS;
    }

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'text' => $schema->string()->required(),
        ];
    }
}
