<?php

declare(strict_types=1);

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * The welcome a business gets the moment it is created — the first mail of
 * the relationship, sent instantly to the account's address. One message,
 * one next step: reassure the choice and point at connecting WhatsApp.
 */
class BusinessWelcome extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    /**
     * The message knows WHAT to say; who reads it is the channel's business,
     * so only the model comes in. Public so the views read `$model` directly.
     */
    public function __construct(
        public Model $model,
    ) {}

    /**
     * The subject names the business: a mail that names its reader stands out
     * in the inbox where a generic line reads as noise.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('mail.business_welcome.subject', ['name' => $this->model->name]),
        );
    }

    /**
     * An own HTML view and not markdown: the brand chrome (band, card, button)
     * needs full control of the markup. The text view keeps a plain fallback.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.welcome.business',
            text: 'emails.welcome.business-text',
        );
    }
}
