<?php

declare(strict_types=1);

namespace App\Messaging\Channels;

use App\Messaging\Channel;
use Illuminate\Support\Facades\Mail;

class Email extends Channel
{
    /**
     * Arma el Mailable declarado en `$message` y lo pone en el correo.
     *
     * Lo único que sabe esta clase es CÓMO se manda un mail. El idioma llega
     * resuelto y la falla la atrapa el padre: acá no hay try/catch ni
     * `app()->getLocale()` porque eso es igual para todos los canales.
     *
     * El destinatario lo pone el canal y no el mensaje: el mismo correo tiene
     * que poder ir a cualquiera, y el dato vive en un solo lugar.
     */
    protected function deliver(string $locale): void
    {
        Mail::to($this->receives)
            ->locale($locale)
            ->send(new $this->message($this->model));
    }
}
