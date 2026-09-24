<?php

declare(strict_types=1);

namespace App\Ai\Agents;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Attributes\Model;
use Laravel\Ai\Attributes\Provider;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Enums\Lab;
use Laravel\Ai\Promptable;
use Stringable;

/**
 * Runs ONCE per trade: what its customers ask that the universal list does
 * not already cover. Every business of that trade shares the result, so a
 * new trade needs no one to write its topics.
 */
#[Provider(Lab::OpenAI)]
#[Model('gpt-6-astra')]
class ActivityIntentDesigner implements Agent, HasStructuredOutput
{
    use Promptable;

    public function instructions(): Stringable|string
    {
        return <<<'INSTRUCTIONS'
            Te dan un rubro de negocio y la lista de intenciones universales
            que ya existen para cualquier negocio. Devolvés las intenciones
            PROPIAS de ese rubro: lo que sus clientes suelen consultar por
            WhatsApp y que ninguna universal cubre bien. Entre 0 y 8; menos
            es mejor que repetir. Nunca una variante de una universal ni un
            servicio o producto puntual (eso sale del catálogo de cada
            negocio): tienen que ser motivos de consulta.

            name: 2 a 5 palabras, español neutro, con mayúscula inicial
            ("Preparación para el estudio", "Cuidado posterior").
            description: una línea que diga qué entra en esa intención.
            INSTRUCTIONS;
    }

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'intents' => $schema->array()
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
