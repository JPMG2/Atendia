<?php

declare(strict_types=1);

namespace App\Ai\Agents;

use App\Ai\Middleware\ModelOrchestrator;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Attributes\Model;
use Laravel\Ai\Attributes\Provider;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasMiddleware;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Enums\Lab;
use Laravel\Ai\Promptable;
use Stringable;

/**
 * Writes the description a catalog item is missing, for the whole batch in ONE
 * call: the assistant reads these lines to a customer, and an item with no
 * description is answered with its bare name.
 */
#[Provider(Lab::OpenAI)]
#[Model('gpt-6-astra')]
class CatalogCopywriter implements Agent, HasMiddleware, HasStructuredOutput
{
    use Promptable;

    /**
     * @return array<int, object>
     */
    public function middleware(): array
    {
        return [new ModelOrchestrator];
    }

    public function instructions(): Stringable|string
    {
        return <<<'INSTRUCTIONS'
            You write short catalog descriptions for a small business, in the
            same Spanish the names are written in. One or two sentences per
            item, under 200 characters, plain and concrete: what it is and
            what the customer gets. Never invent a price, a duration, a
            guarantee, a brand or a medical claim — you only have the name and
            the trade of the business. Never use emoji, never address the
            business owner, and write for the customer who will read it over
            WhatsApp. Return one description per name you are given, keeping
            the name exactly as it arrived.
            INSTRUCTIONS;
    }

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'items' => $schema->array()
                ->items(
                    $schema->object(fn ($schema): array => [
                        'name' => $schema->string()->required(),
                        'description' => $schema->string()->required(),
                    ])
                )
                ->required(),
        ];
    }
}
