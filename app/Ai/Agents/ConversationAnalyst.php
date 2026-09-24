<?php

declare(strict_types=1);

namespace App\Ai\Agents;

use App\Enums\CustomerSentiment;
use App\Enums\QuestionResolution;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Attributes\Model;
use Laravel\Ai\Attributes\Provider;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Enums\Lab;
use Laravel\Ai\Promptable;
use Stringable;

/**
 * Reads a FINISHED stretch of a thread once and splits it in two: each real
 * question rewritten to stand on its own (the semantic part) and who solved
 * it (the contextual part). A lone message cannot carry that; the whole
 * stretch can.
 */
#[Provider(Lab::OpenAI)]
#[Model('gpt-6-astra')]
class ConversationAnalyst implements Agent, HasStructuredOutput
{
    use Promptable;

    /** @param  array<string, array{id: int, name: string, description: string}>  $intents */
    public function __construct(private array $intents = []) {}

    public function instructions(): Stringable|string
    {
        $menu = collect($this->intents)
            ->map(fn (array $intent, string $key): string => "- {$key}: {$intent['name']} ({$intent['description']})")
            ->implode("\n");

        return <<<INSTRUCTIONS
            Analizás un tramo terminado de una charla de WhatsApp entre un
            cliente y un negocio. Cada línea numerada es un mensaje del tramo;
            lo que aparece como contexto previo ya se analizó y solo sirve para
            entender el tramo.

            questions: cada consulta REAL del cliente en el tramo (pide
            información o algo del negocio). No cuentan saludos, gracias,
            confirmaciones, respuestas a lo que le preguntaron ni datos que
            le pidieron. Si la misma consulta se repite, va una sola vez.
            Para cada una:
            - message: el número del mensaje del cliente donde la hizo.
            - question: la consulta reescrita completa, que se entienda sin
              leer la charla, en español neutro, sin datos personales.
              "¿y los sábados?" hablando de horarios es "¿Abren los sábados?".
              Cada mensaje trae su fecha entre paréntesis: un "hoy", "mañana" o
              "ayer" se reescribe con el día concreto ("¿abren hoy?" un domingo
              es "¿Abren los domingos?").
            - intent: la clave de la intención que mejor le queda de la lista
              de abajo, o vacío si ninguna encaja de verdad.
            - new_intent / new_intent_description: SOLO si intent quedó vacío,
              el motivo de consulta en 2 a 5 palabras, general y reutilizable
              para otros clientes ("Estacionamiento para clientes"), y una
              línea que diga qué entra. Si elegiste una intención, van vacíos.
            - subject: el servicio, producto o cosa concreta por la que
              pregunta, en 1 a 5 palabras ("Perfil tiroideo"), o vacío si no
              pregunta por algo puntual.
            - resolved_by: assistant si el asistente la respondió bien con
              datos concretos; team si la respondió una persona del equipo;
              nobody si nadie la respondió o la respuesta fue vaga, equivocada
              o solo una derivación.
            - answer: SOLO si resolved_by es team, lo que respondió el equipo
              reescrito como respuesta general que le sirva a cualquier
              cliente, sin nombres, teléfonos ni datos de este cliente. Si
              la respuesta vale solo para esa fecha ("hoy cerramos", un
              feriado, "mañana no hay turnos"), va vacío: no sirve para
              siempre. Si no, vacío.

            sentiment: cómo terminó el cliente al final del tramo: positive,
            neutral o negative.

            Intenciones:
            {$menu}
            INSTRUCTIONS;
    }

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'questions' => $schema->array()
                ->items(
                    $schema->object(fn ($schema): array => [
                        'message' => $schema->integer()->required(),
                        'question' => $schema->string()->required(),
                        'intent' => $schema->string()->required(),
                        'new_intent' => $schema->string()->required(),
                        'new_intent_description' => $schema->string()->required(),
                        'subject' => $schema->string()->required(),
                        'resolved_by' => $schema->string()->enum(array_column(QuestionResolution::cases(), 'value'))->required(),
                        'answer' => $schema->string()->required(),
                    ])
                )
                ->required(),
            'sentiment' => $schema->string()->enum(array_column(CustomerSentiment::cases(), 'value'))->required(),
        ];
    }
}
