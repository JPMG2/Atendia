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
 * Judges ONE customer message in its exchange: is it a real question the
 * owner's statistics should count, does the assistant need to be taught
 * the answer, and under what short topic it belongs. The word list already
 * dropped the obvious small talk; this sees what a list cannot — context.
 */
#[Provider(Lab::OpenAI)]
#[Model('gpt-6-astra')]
class MessageTriage implements Agent, HasStructuredOutput
{
    use Promptable;

    public function instructions(): Stringable|string
    {
        return <<<'INSTRUCTIONS'
            Analizás UN mensaje que un cliente le escribió por WhatsApp a un
            negocio, junto con lo que el asistente le había dicho antes y lo que
            le respondió después. Decidís tres cosas con criterio de dueño de
            negocio: solo cuenta lo que le sirve para entender qué le piden.

            is_enquiry: true SOLO si el cliente pide información o algo del
            negocio (precios, horarios, servicios, stock, turnos, ubicación,
            envíos, pagos, requisitos...). Es false si solo confirma, agradece,
            saluda, se despide, responde sí/no a una pregunta del asistente,
            da un dato que le pidieron (nombre, correo, fecha), manda un
            reclamo sin pregunta, o escribe algo sin relación con el negocio.
            Ejemplo: "sí" respondiendo "¿Querés que te agende?" es false;
            "¿y los sábados?" siguiendo una charla de horarios es true.

            needs_teaching: true SOLO si is_enquiry es true Y el asistente no
            pudo responderla con información del negocio (dijo que no lo pudo
            confirmar, derivó al equipo, dio una respuesta vaga o equivocada).
            Si la respondió bien con datos concretos, es false.

            topic: si is_enquiry es true, el tema en 2 a 5 palabras, en
            español neutro, sin signos, empezando con mayúscula y sin
            detalles personales, de modo que la misma consulta escrita de
            distintas formas reciba el mismo tema ("Horario de hoy",
            "Precio del perfil tiroideo", "Envíos a domicilio"). Si
            is_enquiry es false, devolvé topic vacío.
            INSTRUCTIONS;
    }

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'is_enquiry' => $schema->boolean()->required(),
            'needs_teaching' => $schema->boolean()->required(),
            'topic' => $schema->string()->required(),
        ];
    }
}
