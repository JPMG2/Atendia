<?php

declare(strict_types=1);

namespace App\Messaging;

use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;
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
     * El tipo que ESTE canal le exige al mensaje.
     *
     * `null` = alcanza con que la clase exista. Cada canal lo pisa con lo suyo
     * (mail exige un Mailable) sin que el contrato crezca: es una constante que
     * se sobreescribe, no un método abstracto más.
     */
    protected const MESSAGE_CONTRACT = null;

    /**
     * @param  Model  $model  El registro del que habla el mensaje.
     * @param  array<int, string>  $receives  A quién se le manda. Lo decide el canal, no el mensaje.
     * @param  class-string  $message  La clase del mensaje a armar (para mail, el Mailable).
     *
     * @throws InvalidArgumentException si `$message` no es una clase usable por este canal.
     */
    public function __construct(
        protected Model $model,
        protected array $receives,
        protected string $message
    ) {
        $this->guardMessage($message);
    }

    /**
     * Se planta si `$message` no cumple lo que este canal necesita.
     *
     * `$message` es lo único del constructor que PHP NO puede garantizar: es un
     * `string`, y que ese texto sea el nombre de una clase usable es una promesa
     * que nadie verifica. Sin este chequeo, un typo explota recién al mandar
     * ("Class not found"), y una clase que no es un mensaje se construye igual y
     * revienta más adentro, con un error que habla de otra cosa y apunta al
     * lugar equivocado.
     *
     * Falla al CONSTRUIR y no en `send()`: un mensaje mal declarado es un error
     * de programación, no un servicio caído. El `try/catch` de `send()` está para
     * que un canal caído no voltee el guardado, no para tapar un nombre mal
     * escrito — ese tiene que dar la cara.
     */
    private function guardMessage(string $message): void
    {
        if (! class_exists($message)) {
            throw new InvalidArgumentException(
                sprintf('[%s] no existe la clase de mensaje "%s".', static::class, $message)
            );
        }

        $contract = static::MESSAGE_CONTRACT;

        if ($contract !== null && ! is_a($message, $contract, true)) {
            throw new InvalidArgumentException(
                sprintf('[%s] el mensaje "%s" tiene que ser un %s.', static::class, $message, $contract)
            );
        }
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
