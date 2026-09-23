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
 * Security receipt after every password change, with the reset one click
 * away for when the owner did not make it.
 */
class AccountPasswordChanged extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public Model $model,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('mail.account.password_changed.subject'),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.account.password-changed',
            text: 'emails.account.password-changed-text',
        );
    }
}
