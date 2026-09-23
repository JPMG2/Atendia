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
 * The "still with you" note once the new login address is confirmed —
 * the same gesture the business contact change makes.
 */
class AccountEmailUpdated extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public Model $model,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('mail.account.email_updated.subject'),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.account.email-updated',
            text: 'emails.account.email-updated-text',
        );
    }
}
