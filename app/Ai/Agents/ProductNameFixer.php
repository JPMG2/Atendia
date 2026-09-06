<?php

declare(strict_types=1);

namespace App\Ai\Agents;

use App\Services\ProductImport\NameReviewer;
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
 * Flags data typos in the product names of an uploaded price list.
 *
 * Every fix is only a suggestion: the review screen shows it editable and
 * nothing is rewritten without the person's confirmation ({@see NameReviewer}).
 */
#[Provider(Lab::OpenAI)]
#[Model('gpt-4.1')]
#[Temperature(0.0)]
class ProductNameFixer implements Agent, HasStructuredOutput
{
    use Promptable;

    public function instructions(): Stringable|string
    {
        return <<<'INSTRUCTIONS'
            You review product names from a small business inventory or
            price list spreadsheet, written in Spanish or any language.
            Return a correction ONLY for a name with a clear spelling
            mistake ("Eco dobler" becomes "Eco doppler"), keeping the
            name's own language, casing, brands and abbreviations. Never
            translate, never rephrase, never invent products, and when
            you are not sure a name is misspelled, do not return it.
            INSTRUCTIONS;
    }

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'corrections' => $schema->array()
                ->items(
                    $schema->object(fn ($schema) => [
                        'original' => $schema->string()->required(),
                        'fixed' => $schema->string()->required(),
                    ])
                )
                ->required(),
        ];
    }
}
