<?php

declare(strict_types=1);

namespace App\Messaging;

use Illuminate\Database\Eloquent\Model;
use Throwable;

/**
 * Un canal por el que sale un mensaje del sistema: mail hoy, WhatsApp mañana.
 *
 * La clase escribe el RITUAL completo —capturar el idioma, aislar la falla— y
 * deja un solo hueco, `deliver()`, para lo único que cada canal hace distinto:
 * poner el mensaje en su vía.
 *
 * Por eso tiene UN método abstracto y tiene que seguir teniendo uno: sumar un
 * canal es sumar una SUBCLASE, nunca un método nuevo acá. Si el día de mañana
 * apareciera un `deliverWhatsapp()`, `Email` quedaría obligada a implementarlo
 * —vacío, porque no sabe mandar un WhatsApp— y ese método vacío devolvería
 * `null` sin avisar: el mensaje no sale y nadie se entera.
 */
abstract class Channel
{
    /**
     * @param  Model  $model  El registro del que habla el mensaje.
     * @param  array<int, string>  $receives  A quién se le manda. Lo decide el canal, no el mensaje.
     * @param  class-string  $message  La clase del mensaje a armar (para mail, el Mailable).
     */
    public function __construct(
        protected Model $model,
        protected array $receives,
        protected string $message
    ) {
        //
    }

    /**
     * Manda el mensaje por este canal.
     *
     * Es el método que llama el resto de la app, y es el mismo para todos los
     * canales: lo que cambia es el `deliver()` de cada uno.
     */
    public function send(): void
    {
        // El idioma se captura ACÁ, en el request, y viaja con el mensaje. Si no,
        // un envío encolado se arma en el worker —donde no hay sesión ni request—
        // y sale en el idioma por defecto en vez del que eligió la persona.
        $locale = app()->getLocale();

        try {
            $this->deliver($locale);
        } catch (Throwable $e) {
            // Un canal caído no puede voltear la operación que lo disparó: la
            // compañía se guardó igual. Queda en el log, no en la cara del
            // usuario.
            report($e);
        }
    }

    /**
     * Lo único que cada canal sabe hacer distinto.
     */
    abstract protected function deliver(string $locale): void;
}
