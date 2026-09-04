<?php

declare(strict_types=1);

namespace App\Ai\Agents;

use App\Services\ProductImport\ColumnMapper;
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
 * Maps the columns of an uploaded price list onto the universal product core.
 *
 * Only the columns the deterministic heuristics could not resolve reach this
 * agent ({@see ColumnMapper}); anything it cannot
 * place lands on `extra`, which is never a loss — extra data becomes product
 * knowledge the assistant can answer from.
 */
#[Provider(Lab::OpenAI)]
#[Model('gpt-4.1')]
#[Temperature(0.0)]
class ProductColumnMapper implements Agent, HasStructuredOutput
{
    public const array TARGETS = ['name', 'price', 'stock', 'description', 'extra'];

    use Promptable;

    public function instructions(): Stringable|string
    {
        return <<<'INSTRUCTIONS'
            You classify the columns of a small business inventory or price
            list spreadsheet, written in Spanish or any language. For each
            given column (its header plus sample values) pick exactly one
            target: "name" (the product's name), "price" (unit price),
            "stock" (available quantity), "description", or "extra" for
            anything else — never invent another target and never drop a
            column. At most one column may map to each of name, price, stock
            and description; when in doubt, prefer "extra".
            INSTRUCTIONS;
    }

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'mappings' => $schema->array()
                ->items(
                    $schema->object(fn ($schema) => [
                        'column' => $schema->string()->required(),
                        'target' => $schema->string()->enum(self::TARGETS)->required(),
                    ])
                )
                ->required(),
        ];
    }
}
