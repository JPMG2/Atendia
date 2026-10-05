<?php

declare(strict_types=1);

namespace App\Ai\Agents;

use App\Traits\RunsAssignedModel;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Attributes\Model;
use Laravel\Ai\Attributes\Provider;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Enums\Lab;
use Laravel\Ai\Promptable;
use Stringable;

/**
 * Drafts the presentation a business introduces itself with. It proposes and
 * never saves: the owner reviews the line before it reaches the assistant,
 * because that line is what a customer reads over WhatsApp.
 */
#[Provider(Lab::OpenAI)]
#[Model('gpt-6-astra')]
class BusinessBioWriter implements Agent, HasStructuredOutput
{
    use Promptable, RunsAssignedModel;

    public function instructions(): Stringable|string
    {
        return <<<'INSTRUCTIONS'
            You write the short presentation a small business introduces itself
            with, in the same Spanish its name and trade are written in. Two or
            three sentences, under 320 characters, plain and concrete: what the
            business does, for whom, and why it is worth choosing. You only
            have the name, the trade and what it offers — never invent a price,
            a founding year, an address, a phone, an award, a number of
            customers, a guarantee or a medical claim. Never use emoji, never
            address the owner, and write it to be read by a customer over
            WhatsApp.
            INSTRUCTIONS;
    }

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'description' => $schema->string()->required(),
        ];
    }
}
