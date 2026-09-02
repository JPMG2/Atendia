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
     * The message knows WHAT to say; who reads it is the channel's business, so
     * only the model comes in. Public on purpose: Laravel hands a Mailable's
     * public properties to its view, so `emails.new.company` reads `$model`
     * without a `with()`.
     */
    public function __construct(
        public Model $model,
    ) {}

    /**
     * The subject carries the company's name: a mail that names its reader
     * stands out in the inbox where a generic line reads as noise.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('mail.new_company.subject', ['name' => $this->model->legal_name]),
        );
    }

    /**
     * An own HTML view and not markdown: the brand chrome (band, card, button)
     * needs full control of the markup. The text view keeps a plain fallback.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.new.company',
            text: 'emails.new.company-text',
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
