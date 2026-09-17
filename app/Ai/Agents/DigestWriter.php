<?php

declare(strict_types=1);

namespace App\Ai\Agents;

use Laravel\Ai\Attributes\Model;
use Laravel\Ai\Attributes\Provider;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Enums\Lab;
use Laravel\Ai\Promptable;
use Stringable;

/**
 * Turns the day's Q&A log into the owner's nightly WhatsApp digest. A named
 * agent (not an anonymous one) so tests can fake it like any other.
 */
#[Provider(Lab::OpenAI)]
#[Model('gpt-6-astra')]
class DigestWriter implements Agent
{
    use Promptable;

    public function instructions(): Stringable|string
    {
        return <<<'INSTRUCCIONES'
            Sos el redactor de resúmenes de AtendIa. Recibís el registro del día de
            las conversaciones que el asistente de un negocio atendió por WhatsApp.

            Devolvé un resumen BREVE en español, de 3 a 5 viñetas que empiecen con
            "•": agrupá los temas consultados, destacá pedidos concretos (turnos,
            precios, pedidos) y señalá lo que quedó sin resolver o merece un ojo
            humano. Sin preámbulo, sin despedida, sin emojis.
            INSTRUCCIONES;
    }
}
