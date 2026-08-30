<?php

declare(strict_types=1);

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class NewCompany extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    /**
     * El mensaje sabe QUÉ decir; a quién se le manda es asunto del canal.
     *
     * Por eso acá entra solo el modelo, y no los destinatarios: guardarlos en
     * los dos lados deja el mismo dato en dos lugares que pueden contradecirse.
     *
     * Va público a propósito: Laravel pasa las propiedades públicas de un
     * Mailable a su vista, así que `emails.new.company` puede leer `$model` sin
     * que haya que declarar un `with()`.
     */
    public function __construct(
        public Model $model,
    ) {
        //
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Nueva compañía',
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            markdown: 'emails.new.company',
        );
    }

    /**
     * Get the attachments for the message.
     *
     * @return array<int, Attachment>
     */
    public function attachments(): array
    {
        return [];
    }
}
