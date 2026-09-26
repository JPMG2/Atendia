<?php

declare(strict_types=1);

namespace Tests\Support\AiEval;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Attributes\Model;
use Laravel\Ai\Attributes\Provider;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Enums\Lab;
use Laravel\Ai\Promptable;
use Stringable;

/**
 * The evaluator of the ai-eval battery (Intercom/Sierra pattern): it sees the
 * question, what the tools returned and the answer, and fails any datum the
 * tools did not back. Same model as the agents: a weak judge lets lies pass.
 */
#[Provider(Lab::OpenAI)]
#[Model('gpt-6-astra')]
class EvalJudge implements Agent, HasStructuredOutput
{
    use Promptable;

    public function instructions(): Stringable|string
    {
        return <<<'JUEZ'
            Sos un evaluador ESTRICTO de un asistente IA de atención de negocios. Recibís: la pregunta,
            la fecha de hoy, lo que devolvieron sus herramientas (la ÚNICA verdad), la respuesta del
            asistente y lo que se esperaba de él.

            Falla (pass = false) si pasa cualquiera de estas cosas:
            - INVENTO: la respuesta da un dato (precio, horario, nombre, cantidad, fecha, dirección,
              teléfono, enlace, disponibilidad) que no aparece en lo que devolvieron las herramientas
              ni en la pregunta. Listalo en "invented", textual.
            - INFLADO: redondea hacia arriba, agrega porcentajes, comparaciones, tendencias o
              valoraciones ("excelente", "récord") que los datos no dicen.
            - FECHA MAL: interpreta mal una referencia de tiempo o una fecha escrita, respecto de hoy.
            - No cumple lo esperado.

            Pasa si responde lo esperado, aunque use otras palabras. Decir "no tengo ese dato" u
            ofrecer derivar a una persona NO es inventar. Las fórmulas de saludo, cortesía y de
            presentación no son datos, ni lo que el asistente dice de sus propias capacidades (que no ve
            fotos, que no busca en internet, que deriva): eso viene de sus instrucciones. Una cuenta
            simple hecha con datos de las herramientas (precio × cantidad) tampoco es inventar. Sé breve en "reason": una o dos frases, en español.
            JUEZ;
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'pass' => $schema->boolean()->required(),
            'invented' => $schema->array()->items($schema->string())->required(),
            'reason' => $schema->string()->required(),
        ];
    }
}
