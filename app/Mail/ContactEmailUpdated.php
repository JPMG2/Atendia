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
 * The note a business gets when its contact address CHANGES, sent to the new
 * inbox. One message, two jobs: prove the new channel works and hand whoever
 * did not make the change a way to react.
 */
class ContactEmailUpdated extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    /**
     * The message knows WHAT to say; who reads it is the channel's business,
     * so only the model comes in. Public so the views read `$model` directly.
     */
    public function __construct(
        public Model $model,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('mail.contact_updated.subject', ['name' => $this->model->name]),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.updated.contact',
            text: 'emails.updated.contact-text',
        );
    }
}
