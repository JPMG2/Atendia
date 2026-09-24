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
 * Decides which unanswered questions are the SAME question, only where the
 * vectors cannot: "¿abren los sábados?" and "¿abren los domingos?" sit
 * closer (0.85) than two real paraphrases (0.73), measured 2026-09-24.
 */
#[Provider(Lab::OpenAI)]
#[Model('gpt-6-astra')]
class QuestionMatcher implements Agent, HasStructuredOutput
{
    use Promptable;

    public function instructions(): Stringable|string
    {
        return <<<'INSTRUCTIONS'
            Recibís preguntas nuevas de clientes de un negocio, numeradas, y
            sugerencias existentes con su id. Dos preguntas son LA MISMA solo
            si una única respuesta del negocio sirve para las dos ("¿Aceptan
            tarjeta?" y "¿Puedo pagar con tarjeta?" sí; "¿Abren los sábados?"
            y "¿Abren los domingos?" no; el precio de dos estudios distintos
            no). Para cada pregunta nueva:
            - question: su número.
            - suggestion: el id de la sugerencia existente que es la misma
              pregunta, o 0 si ninguna.
            - same_as: si no tiene sugerencia, el número de una pregunta
              nueva ANTERIOR que sea la misma, o 0.
            INSTRUCTIONS;
    }

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'matches' => $schema->array()
                ->items(
                    $schema->object(fn ($schema): array => [
                        'question' => $schema->integer()->required(),
                        'suggestion' => $schema->integer()->required(),
                        'same_as' => $schema->integer()->required(),
                    ])
                )
                ->required(),
        ];
    }
}
